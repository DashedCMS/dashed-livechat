<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Filament\Pages\ChatAgentPlayground;

/**
 * Structurele test: de pagina mount zonder fouten en lijst de AI-agent(en)
 * voor de actieve site op. De live AI-round-trip (send()) raakt de echte
 * Claude API en wordt bewust NIET hier getest — dat is handmatig CMS-werk.
 */
it('mount zonder fouten en toont de AI-agents van de site', function () {
    $agent = ChatAgent::create([
        'site_id' => 'main',
        'type' => 'ai',
        'name' => 'Testbot',
        'is_active' => true,
        'guardrail_mode' => 'strict',
    ]);

    $page = new ChatAgentPlayground();
    $page->mount();

    expect($page->siteId)->toBe('main')
        ->and($page->agents)->toHaveCount(1)
        ->and($page->agents[0]['id'])->toBe($agent->id)
        ->and($page->agents[0]['name'])->toBe('Testbot')
        ->and($page->selectedAgentId)->toBe($agent->id)
        ->and($page->turns)->toBe([])
        ->and($page->errorMessage)->toBeNull();

    // Guardrail-uitleg: 1 regel, afgeleid van guardrail_mode van de agent.
    expect($page->getGuardrailExplanationProperty())->toContain('Streng');
});

it('laat inactieve en menselijke agents buiten de lijst', function () {
    ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Inactief', 'is_active' => false]);
    ChatAgent::create(['site_id' => 'main', 'type' => 'human', 'name' => 'Mens', 'is_active' => true]);

    $page = new ChatAgentPlayground();
    $page->mount();

    expect($page->agents)->toBe([]);
});

it('resetSandbox maakt het gesprek en de fout leeg', function () {
    $page = new ChatAgentPlayground();
    $page->mount();

    $page->sandboxToken = 'some-token';
    $page->turns = [['role' => 'visitor', 'content' => 'hoi', 'tool_trace' => []]];
    $page->errorMessage = 'iets ging mis';

    $page->resetSandbox();

    expect($page->sandboxToken)->toBeNull()
        ->and($page->turns)->toBe([])
        ->and($page->errorMessage)->toBeNull();
});

it('markeert sandbox-conversaties als zodanig en telt ze niet mee als gewoon gesprek', function () {
    $agent = ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Testbot', 'is_active' => true]);

    $sandbox = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'is_sandbox' => true,
        'ai_agent_id' => $agent->id,
    ]);
    $real = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
    ]);

    expect($sandbox->fresh()->is_sandbox)->toBeTrue()
        ->and($real->fresh()->is_sandbox)->toBeFalse();

    $stats = app(\Dashed\DashedLivechat\Services\ChatAnalyticsService::class)->forSite('main');
    expect($stats['conversations'])->toBe(1);
});
