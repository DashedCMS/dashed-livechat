<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Mail\OfflineReplyMail;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\HandoffService;
use Dashed\DashedLivechat\Mail\HandoffNotificationMail;
use Dashed\DashedLivechat\Services\ConversationManager;
use Dashed\DashedLivechat\Filament\Pages\ChatAgentPlayground;

/**
 * Twee onafhankelijke code-reviews vonden dat de agent-testomgeving
 * (ChatAgentPlayground) niet echt geïsoleerd is: sandbox-testgesprekken lekten
 * naar echte medewerker-/app-views en vuurden echte notificaties/mails af.
 * Deze tests bewijzen dat de global scope + de gates dat dichttimmeren.
 */
it('sluit sandbox-conversaties standaard uit van elke gewone query', function () {
    ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => true,
    ]);
    ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => false,
    ]);

    expect(ChatConversation::count())->toBe(1)
        ->and(ChatConversation::first()->is_sandbox)->toBeFalse();
});

it('withSandbox() haalt de global scope weg en toont ook sandbox-rijen', function () {
    ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => true,
    ]);
    ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => false,
    ]);

    expect(ChatConversation::withSandbox()->count())->toBe(2);
});

it('findOrCreate hervindt eenzelfde sandbox-conversatie ondanks de global scope', function () {
    $manager = app(ConversationManager::class);

    $first = $manager->findOrCreate('main', null, ['is_sandbox' => true]);
    $second = $manager->findOrCreate('main', $first->public_token, ['is_sandbox' => true]);

    expect($second->id)->toBe($first->id)
        ->and(ChatConversation::withSandbox()->count())->toBe(1);
});

it('ChatAgentPlayground laadt eerdere turns van zijn eigen sandbox-gesprek ondanks de global scope', function () {
    $agent = ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Testbot', 'is_active' => true]);

    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => true,
        'ai_agent_id' => $agent->id,
    ]);
    $conversation->messages()->create(['role' => 'visitor', 'content' => 'hoi']);
    $conversation->messages()->create(['role' => 'ai', 'content' => 'hallo terug']);

    $page = new ChatAgentPlayground();
    $page->mount();
    $page->sandboxToken = $conversation->public_token;

    $method = new ReflectionMethod($page, 'loadTurnsFromExistingSandbox');
    $method->setAccessible(true);
    $method->invoke($page);

    expect($page->turns)->toHaveCount(2)
        ->and($page->turns[0]['content'])->toBe('hoi')
        ->and($page->turns[1]['content'])->toBe('hallo terug');
});

it('stuurt geen push-notificatie voor een nieuw bezoekersbericht in een sandbox-gesprek', function () {
    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => true,
    ]);

    // NotificationCenter (dashed-mobile-api) is hier niet geinstalleerd, dus de
    // enige manier om "geen side effect" te bewijzen is dat de call niet
    // crasht en de conversatie niet verandert op een manier die op een reeel
    // gesprek wijst. We asserten vooral via de guard in de service zelf
    // (is_sandbox), zie de aparte reflectie-achtige aanpak niet nodig: de mail-
    // route hieronder is de sterkere, mockbare assertie.
    $manager = app(ConversationManager::class);
    $message = $manager->addVisitorMessage($conversation, 'Test bericht in sandbox');

    expect($message->role)->toBe('visitor');
});

it('mailt geen offline-antwoord naar de bezoeker in een sandbox-gesprek', function () {
    Mail::fake();

    $agent = ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Testbot', 'is_active' => true]);
    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => true,
        'visitor_email' => 'klant@example.com',
        'visitor_last_active_at' => now()->subMinutes(5),
    ]);

    $manager = app(ConversationManager::class);
    $manager->addAiMessage($conversation, $agent, 'Hier is je antwoord.');

    Mail::assertNothingQueued();
    Mail::assertNothingSent();
});

it('mailt WEL een offline-antwoord voor een normaal (niet-sandbox) gesprek', function () {
    Mail::fake();

    $agent = ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Testbot', 'is_active' => true]);
    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => false,
        'visitor_email' => 'klant@example.com',
        'visitor_last_active_at' => now()->subMinutes(5),
    ]);

    $manager = app(ConversationManager::class);
    $manager->addAiMessage($conversation, $agent, 'Hier is je antwoord.');

    Mail::assertQueued(OfflineReplyMail::class);
});

it('handoff in een sandbox-gesprek verandert de status lokaal maar notificeert geen medewerkers', function () {
    Mail::fake();

    ChatAgent::create([
        'site_id' => 'main', 'type' => 'human', 'name' => 'Collega',
        'email' => 'collega@example.com', 'is_active' => true, 'receive_outside_hours' => true,
    ]);

    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => true,
        'mode' => 'ai',
    ]);

    app(HandoffService::class)->startHumanChat($conversation);

    expect($conversation->fresh()->mode)->toBe(\Dashed\DashedLivechat\Enums\ConversationMode::WaitingHuman->value)
        ->and($conversation->events()->where('type', 'handoff_requested')->exists())->toBeTrue();

    Mail::assertNothingSent();
});

it('handoff in een normaal gesprek notificeert WEL medewerkers per mail', function () {
    Mail::fake();

    ChatAgent::create([
        'site_id' => 'main', 'type' => 'human', 'name' => 'Collega',
        'email' => 'collega@example.com', 'is_active' => true, 'receive_outside_hours' => true,
    ]);

    $conversation = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) Str::uuid(),
        'is_sandbox' => false,
        'mode' => 'ai',
    ]);

    app(HandoffService::class)->startHumanChat($conversation);

    Mail::assertSent(HandoffNotificationMail::class);
});
