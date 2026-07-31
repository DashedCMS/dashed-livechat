<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\VisitorSession;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\ConversationDetailResource;

function convWithSession(?float $cartTotal): ChatConversation
{
    $conv = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'mode' => 'ai',
        'visitor_session_token' => 'sess-'.\Illuminate\Support\Str::random(6),
    ]);

    if ($cartTotal !== null) {
        VisitorSession::create([
            'site_id' => 'main',
            'token' => $conv->visitor_session_token,
            'cart_total' => $cartTotal,
            'url' => 'https://shop.test/product/1',
            'last_seen_at' => now(),
        ]);
    }

    return $conv->fresh(['visitorSession']);
}

it('geeft een cart-blok terug met totaal + huidige pagina', function () {
    $conv = convWithSession(42.50);

    $arr = (new ConversationDetailResource($conv))->toArray(request());

    expect($arr['cart'])->not->toBeNull()
        ->and($arr['cart']['total'])->toBe(42.5)
        ->and($arr['cart']['current_url'])->toBe('https://shop.test/product/1');
});

it('cart is null zonder bezoeker-sessie', function () {
    $conv = convWithSession(null);

    $arr = (new ConversationDetailResource($conv))->toArray(request());

    expect($arr['cart'])->toBeNull();
});
