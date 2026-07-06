<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Tests\Support\Factories;
use Dashed\DashedLivechat\Ai\Tools\GetStockAndDeliveryTool;

it('geeft beschikbare voorraad terug (stock - reserved)', function () {
    $c = Factories::makeConversation();
    Factories::makeProduct(['slug' => ['nl' => 'trui'], 'use_stock' => true, 'stock' => 8, 'reserved_stock' => 3]);

    $out = app(GetStockAndDeliveryTool::class)->handle(['product' => 'trui'], $c);

    expect($out['found'])->toBeTrue()
        ->and($out['in_stock'])->toBeTrue()
        ->and($out['available'])->toBe(5);
});

it('meldt niet-op-voorraad met verwachte datum', function () {
    $c = Factories::makeConversation();
    Factories::makeProduct(['slug' => ['nl' => 'riem'], 'use_stock' => true, 'stock' => 0, 'reserved_stock' => 0,
        'expected_in_stock_date' => now()->addDays(7)]);

    $out = app(GetStockAndDeliveryTool::class)->handle(['product' => 'riem'], $c);

    expect($out['found'])->toBeTrue()
        ->and($out['in_stock'])->toBeFalse()
        ->and($out['available'])->toBe(0)
        ->and($out['expected_in_stock_date'])->not->toBeNull();
});

it('geeft found=false bij onbekend product', function () {
    $c = Factories::makeConversation();
    $out = app(GetStockAndDeliveryTool::class)->handle(['product' => 'bestaat-niet'], $c);
    expect($out['found'])->toBeFalse();
});

it('geeft found=false bij een niet-gepubliceerd product', function () {
    $c = Factories::makeConversation();
    Factories::makeProduct(['slug' => ['nl' => 'geheim'], 'use_stock' => true, 'stock' => 5, 'reserved_stock' => 0, 'public' => 0]);

    $out = app(GetStockAndDeliveryTool::class)->handle(['product' => 'geheim'], $c);

    expect($out['found'])->toBeFalse();
});
