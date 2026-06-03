<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Models\ChatKnowledgeEntry;

class SearchFaqTool implements ChatTool
{
    public function name(): string
    {
        return 'searchFaq';
    }

    public function description(): string
    {
        return 'Zoek in de veelgestelde vragen (FAQ) van deze website.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string'],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        $term = (string) ($input['query'] ?? '');
        $entries = ChatKnowledgeEntry::query()
            ->where('site_id', $conversation->site_id)
            ->where('is_active', true)
            ->where(
                fn ($q) => $q
                ->where('question', 'like', "%{$term}%")
                ->orWhere('answer', 'like', "%{$term}%")
            )
            ->limit(5)
            ->get(['question', 'answer']);

        return [
            'results' => $entries->map(fn ($e) => [
                'question' => $e->question,
                'answer' => $e->answer,
            ])->all(),
        ];
    }
}
