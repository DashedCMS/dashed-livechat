<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\WebPushSubscription;

function subscriptionPayload(array $overrides = []): array
{
    return array_merge([
        'site_id' => 'main',
        'endpoint' => 'https://push.example.com/abc-123',
        'public_key' => 'p256dh-key',
        'auth_token' => 'auth-secret',
        'content_encoding' => 'aesgcm',
    ], $overrides);
}

it('weigert subscriben zonder ingelogde gebruiker', function () {
    $this->postJson(route('dashed-livechat.web-push.subscribe'), subscriptionPayload())
        ->assertUnauthorized();
});

it('slaat een subscription op voor de ingelogde gebruiker', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('dashed-livechat.web-push.subscribe'), subscriptionPayload())
        ->assertSuccessful();

    expect(WebPushSubscription::where('user_id', $user->id)->where('site_id', 'main')->count())->toBe(1);
});

it('werkt een bestaande subscription bij in plaats van te dupliceren', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('dashed-livechat.web-push.subscribe'), subscriptionPayload());
    $this->actingAs($user)->postJson(route('dashed-livechat.web-push.subscribe'), subscriptionPayload(['auth_token' => 'nieuwe-secret']));

    $subs = WebPushSubscription::where('user_id', $user->id)->get();
    expect($subs)->toHaveCount(1)
        ->and($subs->first()->auth_token)->toBe('nieuwe-secret');
});

it('verwijdert een subscription bij unsubscribe', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->postJson(route('dashed-livechat.web-push.subscribe'), subscriptionPayload());

    $this->actingAs($user)
        ->deleteJson(route('dashed-livechat.web-push.unsubscribe'), ['endpoint' => 'https://push.example.com/abc-123'])
        ->assertSuccessful();

    expect(WebPushSubscription::where('user_id', $user->id)->count())->toBe(0);
});

it('serveert de service worker met het juiste content-type', function () {
    $this->get(route('dashed-livechat.web-push.sw'))
        ->assertSuccessful()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
});
