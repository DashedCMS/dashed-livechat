<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Dashed\DashedLivechat\Tests\Support\Factories;
use Dashed\DashedLivechat\Ai\Tools\StartReturnTool;

it('start een retour voor een geverifieerde order', function () {
    // Order::registerReturn() -> restockOrderProduct() slaat het Product op,
    // wat via de saved-event UpdateProductInformationJob dispatcht. Die job
    // draait normaal synchroon in tests en gebruikt MySQL-only SQL (GREATEST)
    // in Product::calculatePrices(). Zelfde patroon als
    // dashed-ecommerce-core/tests/Feature/ProductWriteTest.php: queue faken
    // zodat de dispatch (de side-effect die we wíllen verifiëren middels de
    // restock) gebeurt zonder die SQL op SQLite te draaien.
    Queue::fake();

    $c = Factories::makeConversation();
    $d = Factories::makeOrderWithProduct(['email' => 'klant@example.com', 'invoice_id' => '3001'], [], qty: 2);

    $out = app(StartReturnTool::class)->handle([
        'orderNumber' => '3001',
        'email' => 'klant@example.com',
        'lines' => [['order_product_id' => $d['orderProduct']->id, 'quantity' => 1]],
        'reason' => 'Te klein',
    ], $c);

    expect($out['ok'])->toBeTrue();
    expect($d['orderProduct']->fresh()->returned_quantity)->toBe(1);
    expect($d['order']->fresh()->retour_status)->toBe('partially_returned');
});

it('weigert bij niet-kloppende order/e-mail (geen retour)', function () {
    $c = Factories::makeConversation();
    $d = Factories::makeOrderWithProduct(['email' => 'klant@example.com', 'invoice_id' => '3002']);

    $out = app(StartReturnTool::class)->handle([
        'orderNumber' => '3002',
        'email' => 'fout@example.com',
        'lines' => [['order_product_id' => $d['orderProduct']->id, 'quantity' => 1]],
    ], $c);

    expect($out['ok'])->toBeFalse();
    expect($d['orderProduct']->fresh()->returned_quantity)->toBe(0);
});

it('vangt te-groot-aantal netjes af', function () {
    $c = Factories::makeConversation();
    $d = Factories::makeOrderWithProduct(['email' => 'klant@example.com', 'invoice_id' => '3003'], [], qty: 1);

    $out = app(StartReturnTool::class)->handle([
        'orderNumber' => '3003',
        'email' => 'klant@example.com',
        'lines' => [['order_product_id' => $d['orderProduct']->id, 'quantity' => 5]],
    ], $c);

    expect($out['ok'])->toBeFalse()
        ->and($out['message'])->toContain('retourneren');
});
