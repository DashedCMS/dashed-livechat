<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;

class GetStockAndDeliveryTool implements ChatTool
{
    public function name(): string
    {
        return 'getStockAndDelivery';
    }

    public function description(): string
    {
        return 'Geef de actuele voorraad en indicatieve levertijd van EEN product. Gebruik dit voor vragen als "is X op voorraad?" of "hoe snel wordt het geleverd?". Zoekt op slug of productnaam.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product' => ['type' => 'string', 'description' => 'De slug of naam van het product.'],
            ],
            'required' => ['product'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        if (! class_exists(Product::class)) {
            return ['found' => false];
        }

        $needle = trim((string) ($input['product'] ?? ''));
        $locale = $conversation->locale ?: app()->getLocale();

        $product = Product::query()
            ->whereJsonContains('site_ids', $conversation->site_id)
            ->where('public', 1)
            ->where(function ($q) use ($needle, $locale) {
                $q->where('slug->' . $locale, $needle)
                    ->orWhere('name->' . $locale, 'like', "%{$needle}%");
            })
            ->first();

        if (! $product) {
            return ['found' => false];
        }

        if ($product->use_stock) {
            $available = max(0, (int) $product->stock - (int) $product->reserved_stock);
            $inStock = $available > 0;
        } else {
            $available = null;
            $inStock = $product->stock_status === 'in_stock';
        }

        return [
            'found' => true,
            'in_stock' => $inStock,
            'available' => $available,
            'expected_in_stock_date' => optional($product->expected_in_stock_date)->format('Y-m-d'),
            'delivery_in_days' => $product->expected_delivery_in_days !== null ? (int) $product->expected_delivery_in_days : null,
        ];
    }
}
