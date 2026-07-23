<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Jobs\SendWebPushJob;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\WebPushService;
use Dashed\DashedLivechat\Models\WebPushPreference;
use Dashed\DashedLivechat\Models\WebPushSubscription;

beforeEach(function () {
    config()->set('dashed-livechat.web_push.public_key', 'test-public');
    config()->set('dashed-livechat.web_push.private_key', 'test-private');
});

function makeAgentWithSubscription(int $userId, array $agentOverrides = [], array $subOverrides = []): WebPushSubscription
{
    ChatAgent::create(array_merge([
        'site_id' => 'main',
        'type' => 'human',
        'name' => 'Collega ' . $userId,
        'email' => "collega{$userId}@example.com",
        'is_active' => true,
        'receive_outside_hours' => true,
        'user_id' => $userId,
    ], $agentOverrides));

    return WebPushSubscription::create(array_merge([
        'user_id' => $userId,
        'site_id' => 'main',
        'endpoint' => 'https://push.example.com/' . Str::uuid(),
        'endpoint_hash' => hash('sha256', (string) Str::uuid()),
        'public_key' => 'p256dh-key',
        'auth_token' => 'auth-secret',
        'content_encoding' => 'aesgcm',
    ], $subOverrides));
}

it('configured() is true met beide sleutels en false zonder', function () {
    expect(app(WebPushService::class)->configured())->toBeTrue();

    config()->set('dashed-livechat.web_push.private_key', null);
    expect(app(WebPushService::class)->configured())->toBeFalse();
});

it('dispatcht een job per abonnee-subscription wanneer het type aanstaat', function () {
    Bus::fake();
    makeAgentWithSubscription(1);
    makeAgentWithSubscription(2);

    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => false,
    ]);

    app(WebPushService::class)->notify($conversation, 'handoff', 'Nieuwe chat', 'Er wacht iemand');

    Bus::assertDispatchedTimes(SendWebPushJob::class, 2);
});

it('slaat een abonnee over die het type heeft uitgezet', function () {
    Bus::fake();
    makeAgentWithSubscription(1);
    WebPushPreference::create([
        'user_id' => 1, 'site_id' => 'main',
        'notify_handoff' => false, 'notify_message' => true, 'notify_new' => false,
    ]);

    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => false,
    ]);

    app(WebPushService::class)->notify($conversation, 'handoff', 'Nieuwe chat', 'Er wacht iemand');

    Bus::assertNotDispatched(SendWebPushJob::class);
});

it('stuurt niets voor een sandbox-gesprek', function () {
    Bus::fake();
    makeAgentWithSubscription(1);

    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => true,
    ]);

    app(WebPushService::class)->notify($conversation, 'handoff', 'Nieuwe chat', 'Er wacht iemand');

    Bus::assertNotDispatched(SendWebPushJob::class);
});

it('stuurt niets zonder geconfigureerde sleutels', function () {
    Bus::fake();
    config()->set('dashed-livechat.web_push.public_key', null);
    makeAgentWithSubscription(1);

    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => false,
    ]);

    app(WebPushService::class)->notify($conversation, 'handoff', 'Nieuwe chat', 'Er wacht iemand');

    Bus::assertNotDispatched(SendWebPushJob::class);
});
