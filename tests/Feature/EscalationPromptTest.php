<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Ai\SystemPromptBuilder;
use Dashed\DashedLivechat\Tests\Support\Factories;

function buildPromptFor(ChatAgent $agent): string
{
    $conversation = Factories::makeConversation();

    return app(SystemPromptBuilder::class)->build($agent, $conversation);
}

it('zet aangezette escalatie-redenen in de prompt', function () {
    $agent = ChatAgent::create([
        'site_id' => 'main', 'type' => 'ai', 'name' => 'Bot',
        'escalate_on_request' => true, 'escalate_on_negative' => true,
        'escalate_on_tool_failure' => false, 'escalate_off_topic' => false,
        'escalation_rules' => null,
    ]);
    $prompt = buildPromptFor($agent);
    expect($prompt)->toContain('ESCALATIE')
        ->toContain('expliciet om een medewerker')
        ->toContain('boos of ontevreden')
        ->not->toContain('buiten de toegestane onderwerpen');
});

it('laat de ESCALATIE-regel weg als niets is aangezet en geen vrije tekst', function () {
    $agent = ChatAgent::create([
        'site_id' => 'main', 'type' => 'ai', 'name' => 'Bot',
        'escalate_on_request' => false, 'escalate_on_negative' => false,
        'escalate_on_tool_failure' => false, 'escalate_off_topic' => false,
        'escalation_rules' => null,
    ]);
    expect(buildPromptFor($agent))->not->toContain('ESCALATIE');
});

it('voegt de vrije tekst toe als extra reden naast de aangezette toggles', function () {
    $agent = ChatAgent::create([
        'site_id' => 'main', 'type' => 'ai', 'name' => 'Bot',
        'escalate_on_request' => false, 'escalate_on_negative' => false,
        'escalate_on_tool_failure' => false, 'escalate_off_topic' => false,
        'escalation_rules' => 'bij vragen over facturen',
    ]);
    $prompt = buildPromptFor($agent);
    expect($prompt)->toContain('ESCALATIE')
        ->toContain('bij vragen over facturen');
});
