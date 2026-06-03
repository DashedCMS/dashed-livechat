<?php

// src/Services/ConversationSummarizer.php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;

class ConversationSummarizer
{
    public function summaryFor(ChatConversation $c): ?string
    {
        $after = (int) config('dashed-livechat.summarize_after', 30);
        $keep = (int) config('dashed-livechat.summary_keep_recent', 10);

        $total = $c->messages()->whereIn('role', ['visitor', 'ai', 'human'])->where('is_internal', false)->count();
        if ($total <= $after) {
            return null;
        }

        $lastId = $c->messages()->max('id');
        $meta = $c->meta ?? [];

        // Hergebruik bestaande samenvatting als er sindsdien weinig nieuws is.
        if (($meta['summary'] ?? null) && ($meta['summary_up_to'] ?? 0) >= ($lastId - $keep)) {
            return $meta['summary'];
        }

        $older = $c->messages()
            ->whereIn('role', ['visitor', 'ai', 'human'])
            ->where('is_internal', false)
            ->orderBy('id')
            ->limit(max(0, $total - $keep))
            ->get();

        $transcript = $older->map(fn (ChatMessage $m) => strtoupper($m->role) . ': ' . $m->content)->implode("\n");

        $summary = Ai::text(
            "Vat dit klantgesprek bondig samen in het Nederlands (max 8 zinnen), behoud feiten en openstaande vragen, geen em-dashes:\n\n" . $transcript,
            ['model' => config('dashed-livechat.summary_model')]
        );

        if (! $summary) {
            return null;
        }

        $meta['summary'] = $summary;
        $meta['summary_up_to'] = $lastId;
        $c->forceFill(['meta' => $meta])->save();

        return $summary;
    }
}
