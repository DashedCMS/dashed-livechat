<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedPages\Models\Page;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;

class GetPageTool implements ChatTool
{
    public function name(): string
    {
        return 'getPage';
    }

    public function description(): string
    {
        return 'Haal de inhoud van een specifieke pagina op via de slug.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'slug' => ['type' => 'string'],
            ],
            'required' => ['slug'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        if (! class_exists(\Dashed\DashedPages\Models\Page::class)) {
            return ['found' => false];
        }

        $locale = $conversation->locale ?: app()->getLocale();

        $page = Page::query()
            ->whereJsonContains('site_ids', $conversation->site_id)
            ->where('slug->' . $locale, $input['slug'] ?? null)
            ->first();

        if (! $page) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'title' => $page->name,
            'url' => rescue(fn () => $page->getUrl(), null, false),
        ];
    }
}
