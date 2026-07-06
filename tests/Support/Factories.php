<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Tests\Support;

use Illuminate\Support\Str;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedEcommerceCore\Models\Product;
use Dashed\DashedEcommerceCore\Models\OrderProduct;
use Dashed\DashedEcommerceCore\Models\ProductGroup;
use Dashed\DashedLivechat\Models\ChatConversation;

final class Factories
{
    public static function makeConversation(array $overrides = []): ChatConversation
    {
        return ChatConversation::create(array_merge([
            'site_id' => 'main',
            'locale' => 'nl',
            'order_lookup_attempts' => 0,
            'public_token' => (string) Str::uuid(),
        ], $overrides));
    }

    public static function makeProductGroup(array $overrides = []): ProductGroup
    {
        return ProductGroup::create(array_merge([
            'name' => ['nl' => 'Testgroep'],
            'slug' => ['nl' => 'testgroep-'.uniqid()],
            'short_description' => ['nl' => ''],
            'description' => ['nl' => ''],
            'content' => ['nl' => ''],
            'search_terms' => ['nl' => ''],
            'site_ids' => ['main'],
        ], $overrides));
    }

    public static function makeProduct(array $overrides = []): Product
    {
        // `name`/`slug` zijn translatable; het Product-model dispatcht bij een
        // echte `create()` een UpdateProductInformationJob die een ProductGroup
        // vereist. Daarom eerst een groep aanmaken, koppelen via
        // `product_group_id`, en de model-events onderdrukken (zoals de bewezen
        // ecommerce-core-tests doen).
        $group = self::makeProductGroup();

        return Product::withoutEvents(function () use ($group, $overrides) {
            return Product::create(array_merge([
                'product_group_id' => $group->id,
                'site_ids' => ['main'],
                'name' => ['nl' => 'Testproduct'],
                'slug' => ['nl' => 'testproduct-'.uniqid()],
                'use_stock' => true,
                'stock' => 5,
                'total_stock' => 5,
                'in_stock' => true,
                'reserved_stock' => 0,
                'stock_status' => 'in_stock',
                'price' => 10.00,
                'current_price' => 10.00,
                'public' => 1,
            ], $overrides));
        });
    }

    /** @return array{order: Order, orderProduct: OrderProduct, product: Product} */
    public static function makeOrderWithProduct(array $orderOverrides = [], array $productOverrides = [], int $qty = 1): array
    {
        $product = self::makeProduct($productOverrides);
        $order = Order::create(array_merge([
            'site_id' => 'main',
            'email' => 'klant@example.com',
            'invoice_id' => '2001',
            'status' => 'paid',
            'fulfillment_status' => 'handled',
            'hash' => 'hash-2001',
        ], $orderOverrides));
        $orderProduct = OrderProduct::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'name' => is_array($product->name) ? ($product->name['nl'] ?? 'Testproduct') : (string) $product->name,
            'quantity' => $qty,
            'returned_quantity' => 0,
        ]);

        return ['order' => $order, 'orderProduct' => $orderProduct, 'product' => $product];
    }
}
