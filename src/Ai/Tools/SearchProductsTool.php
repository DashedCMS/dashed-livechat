<?php

namespace Dashed\DashedLivechat\Ai\Tools;

use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedLivechat\Ai\Contracts\ChatTool;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Ai\Knowledge\Contracts\KnowledgeSearchDriver;

class SearchProductsTool implements ChatTool
{
    public function __construct(private KnowledgeSearchDriver $driver)
    {
    }

    public function name(): string
    {
        return 'searchProducts';
    }

    public function description(): string
    {
        return 'Zoek producten van deze website op trefwoord. Gebruik dit voor vragen over producten, prijzen of beschikbaarheid.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Zoekterm, bv. een productnaam of categorie.'],
                'limit' => ['type' => 'integer', 'description' => 'Max aantal resultaten (default 5).'],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $input, ChatConversation $conversation): array
    {
        if (! class_exists(\Dashed\DashedEcommerceCore\Models\Product::class)) {
            return ['results' => []];
        }

        $limit = (int) ($input['limit'] ?? 5);
        $products = $this->driver->search(
            Product::class,
            ['name', 'short_description'],
            $conversation->site_id,
            (string) ($input['query'] ?? ''),
            $limit
        );

        return [
            'results' => $products->map(function (Product $p) {
                $stock = rescue(fn () => $p->stock(), null, false);

                return [
                    'name' => $p->name,
                    'short_description' => $p->short_description,
                    'price' => $p->current_price ?? $p->price ?? null,
                    'in_stock' => rescue(fn () => $p->inStock(), null, false),
                    // Exact aantal alleen als het echt geteld wordt; anders null
                    // (100000 is de "gewoon op voorraad"-sentinel).
                    'stock' => ($stock !== null && $stock < 100000) ? $stock : null,
                    'url' => rescue(fn () => $p->getUrl(), null, false),
                    'image' => rescue(fn () => mediaHelper()->getSingleMedia($p->firstImage, 'small')?->url, null, false),
                ];
            })->values()->all(),
        ];
    }
}
