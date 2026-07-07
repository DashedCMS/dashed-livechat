<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Tests\Support\Factories;
use Dashed\DashedEcommerceCore\Models\StockNotification;
use Dashed\DashedLivechat\Ai\Tools\SubscribeBackInStockTool;

function makeOutOfStockProduct(): object
{
    return Factories::makeProduct([
        'slug' => ['nl' => 'vaas-uitverkocht'],
        'name' => ['nl' => 'Vaas'],
        'use_stock' => true,
        'stock' => 0,
        'reserved_stock' => 0,
        'in_stock' => false,
        'stock_status' => 'out_of_stock',
    ]);
}

it('meldt de bezoeker aan voor een terug-op-voorraad-melding', function () {
    $c = Factories::makeConversation(['visitor_email' => 'klant@example.com']);
    $product = makeOutOfStockProduct();

    $out = app(SubscribeBackInStockTool::class)->handle(['product' => 'vaas-uitverkocht'], $c);

    expect($out['ok'])->toBeTrue();
    expect(StockNotification::where('product_id', $product->id)->where('email', 'klant@example.com')->exists())->toBeTrue();
});

it('vraagt om een e-mailadres als dat onbekend is', function () {
    $c = Factories::makeConversation(['visitor_email' => null]);
    makeOutOfStockProduct();

    $out = app(SubscribeBackInStockTool::class)->handle(['product' => 'vaas-uitverkocht'], $c);

    expect($out['ok'])->toBeFalse()
        ->and($out['message'])->toContain('e-mailadres');
});

it('simuleert in een sandbox-gesprek zonder echt aan te melden', function () {
    $c = Factories::makeConversation(['visitor_email' => 'klant@example.com', 'is_sandbox' => true]);
    makeOutOfStockProduct();

    $out = app(SubscribeBackInStockTool::class)->handle(['product' => 'vaas-uitverkocht'], $c);

    expect($out['ok'])->toBeTrue()
        ->and($out['simulated'] ?? false)->toBeTrue()
        ->and(StockNotification::count())->toBe(0);
});
