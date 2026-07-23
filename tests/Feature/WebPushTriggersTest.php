<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Jobs\SendWebPushJob;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\HandoffService;
use Dashed\DashedLivechat\Models\WebPushSubscription;
use Dashed\DashedLivechat\Services\ConversationManager;

beforeEach(function () {
    config()->set('dashed-livechat.web_push.public_key', 'test-public');
    config()->set('dashed-livechat.web_push.private_key', 'test-private');

    ChatAgent::create([
        'site_id' => 'main', 'type' => 'human', 'name' => 'Collega',
        'email' => 'collega@example.com', 'is_active' => true,
        'receive_outside_hours' => true, 'user_id' => 1,
    ]);
    WebPushSubscription::create([
        'user_id' => 1, 'site_id' => 'main',
        'endpoint' => 'https://push.example.com/abc', 'endpoint_hash' => hash('sha256', 'https://push.example.com/abc'),
        'public_key' => 'p256dh', 'auth_token' => 'auth', 'content_encoding' => 'aesgcm',
    ]);
});

it('stuurt web push bij een handoff in een normaal gesprek', function () {
    Bus::fake();
    Mail::fake();
    $conversation = ChatConversation::create([
        'site_id' => 'main', 'public_token' => (string) Str::uuid(),
        'is_sandbox' => false, 'mode' => 'ai',
    ]);

    app(HandoffService::class)->startHumanChat($conversation);

    Bus::assertDispatched(SendWebPushJob::class);
});

it('stuurt web push bij een nieuw bezoekersbericht', function () {
    Bus::fake();
    $conversation = ChatConversation::create([
        'site_id' => 'main', 'public_token' => (string) Str::uuid(), 'is_sandbox' => false,
    ]);

    app(ConversationManager::class)->addVisitorMessage($conversation, 'Hallo, is daar iemand?');

    Bus::assertDispatched(SendWebPushJob::class);
});

it('stuurt web push bij een nieuw gesprek alleen als notify_new aanstaat', function () {
    Bus::fake();

    // notify_new staat standaard uit -> geen job.
    app(ConversationManager::class)->findOrCreate('main', null, ['is_sandbox' => false]);
    Bus::assertNotDispatched(SendWebPushJob::class);

    // Zet notify_new aan en maak opnieuw een gesprek aan -> wel een job.
    \Dashed\DashedLivechat\Models\WebPushPreference::create([
        'user_id' => 1, 'site_id' => 'main',
        'notify_handoff' => true, 'notify_message' => true, 'notify_new' => true,
    ]);
    app(ConversationManager::class)->findOrCreate('main', null, ['is_sandbox' => false]);
    Bus::assertDispatched(SendWebPushJob::class);
});

it('stuurt geen web push voor een nieuw gesprek in een sandbox', function () {
    Bus::fake();
    \Dashed\DashedLivechat\Models\WebPushPreference::create([
        'user_id' => 1, 'site_id' => 'main',
        'notify_handoff' => true, 'notify_message' => true, 'notify_new' => true,
    ]);

    app(ConversationManager::class)->findOrCreate('main', null, ['is_sandbox' => true]);

    Bus::assertNotDispatched(SendWebPushJob::class);
});
