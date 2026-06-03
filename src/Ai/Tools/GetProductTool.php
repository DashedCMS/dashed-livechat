<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;

class GetProductTool implements ChatTool
{
    public function name(): string
    {
        return 'getProduct';
    }

    public function description(): string
    {
        return 'Haal details van een specifiek product op via de slug.';
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
        if (! class_exists(\Dashed\DashedEcommerceCore\Models\Product::class)) {
            return ['found' => false];
        }

        $locale = $conversation->locale ?: app()->getLocale();

        $product = Product::query()
            ->whereJsonContains('site_ids', $conversation->site_id)
            ->where('slug->' . $locale, $input['slug'] ?? null)
            ->first();

        if (! $product) {
            return ['found' => false];
        }

        return [
            'found' => true,
            'name' => $product->name,
            'description' => strip_tags((string) $product->description),
            'price' => $product->current_price ?? $product->price ?? null,
            'url' => rescue(fn () => $product->getUrl(), null, false),
        ];
    }
}
