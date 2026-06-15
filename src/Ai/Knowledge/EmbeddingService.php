<?php

namespace Dashed\DashedLivechat\Ai\Knowledge;

use Dashed\DashedAi\Facades\Ai;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\ChatEmbedding;

class EmbeddingService
{
    public function vectorFor(string $text): array
    {
        return Ai::embed($text) ?: [];
    }

    public function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        $n = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] ** 2;
            $nb += $b[$i] ** 2;
        }

        if ($na == 0.0 || $nb == 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }

    public function upsertFor(Model $model, string $text, string $siteId): void
    {
        $hash = sha1($text);

        $existing = ChatEmbedding::where('site_id', $siteId)
            ->where('embeddable_type', $model::class)
            ->where('embeddable_id', $model->getKey())
            ->first();

        if ($existing && $existing->content_hash === $hash) {
            return;
        }

        ChatEmbedding::updateOrCreate(
            ['site_id' => $siteId, 'embeddable_type' => $model::class, 'embeddable_id' => $model->getKey()],
            ['content_hash' => $hash, 'vector' => $this->vectorFor($text)],
        );
    }

    public function searchModels(string $modelClass, string $siteId, string $term, int $limit): Collection
    {
        $queryVec = $this->vectorFor($term);

        if (! $queryVec) {
            return collect();
        }

        $rows = ChatEmbedding::where('site_id', $siteId)
            ->where('embeddable_type', $modelClass)
            ->get();

        $threshold = (float) Customsetting::get('chat_embedding_threshold', $siteId, 0.75);

        $ranked = $rows
            ->map(fn ($row) => [
                'id' => $row->embeddable_id,
                'score' => $this->cosine($queryVec, $row->vector ?? []),
            ])
            ->filter(fn ($row) => $row['score'] >= $threshold)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('id');

        if ($ranked->isEmpty()) {
            return collect();
        }

        $models = $modelClass::whereIn((new $modelClass())->getKeyName(), $ranked)->get()->keyBy->getKey();

        return $ranked->map(fn ($id) => $models->get($id))->filter()->values();
    }
}
