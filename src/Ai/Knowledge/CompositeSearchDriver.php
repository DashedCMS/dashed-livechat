<?php

namespace Dashed\DashedLivechat\Ai\Knowledge;

use Illuminate\Support\Collection;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Ai\Knowledge\Contracts\KnowledgeSearchDriver;

class CompositeSearchDriver implements KnowledgeSearchDriver
{
    public function __construct(
        private DatabaseSearchDriver $fulltext,
        private EmbeddingSearchDriver $embedding,
    ) {
    }

    public function search(string $modelClass, array $columns, string $siteId, string $term, int $limit): Collection
    {
        $driver = Customsetting::get('chat_search_driver', $siteId, 'fulltext');

        if ($driver === 'embedding') {
            $results = rescue(
                fn () => $this->embedding->search($modelClass, $columns, $siteId, $term, $limit),
                collect(),
                false
            );

            if ($results->isNotEmpty()) {
                return $results;
            }
        }

        return $this->fulltext->search($modelClass, $columns, $siteId, $term, $limit);
    }
}
