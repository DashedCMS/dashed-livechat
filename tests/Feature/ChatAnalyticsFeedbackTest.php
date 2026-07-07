<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\ChatEvent;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Services\ChatAnalyticsService;
use Dashed\DashedLivechat\Tests\Support\Factories;

it('telt feedback en berekent % zelf-afgehandeld', function () {
    $c1 = Factories::makeConversation(['mode' => 'ai']);
    $c2 = Factories::makeConversation(['mode' => 'waiting_human']);

    ChatMessage::create(['chat_conversation_id' => $c1->id, 'role' => 'ai', 'content' => 'a', 'feedback' => 'good']);
    ChatMessage::create(['chat_conversation_id' => $c1->id, 'role' => 'ai', 'content' => 'b', 'feedback' => 'bad']);
    ChatMessage::create(['chat_conversation_id' => $c1->id, 'role' => 'ai', 'content' => 'c']); // geen feedback

    ChatEvent::create(['chat_conversation_id' => $c2->id, 'type' => 'handoff_requested', 'payload' => []]);

    $stats = app(ChatAnalyticsService::class)->forSite('main');

    expect($stats['feedback_good'])->toBe(1)
        ->and($stats['feedback_bad'])->toBe(1)
        ->and($stats['escalations'])->toBe(1)
        ->and($stats['conversations'])->toBe(2)
        ->and($stats['self_handled_pct'])->toBe(50); // (2 - 1) / 2 = 50%
});

it('sluit sandbox-gesprekken uit van de feedback-cijfers', function () {
    $sandbox = Factories::makeConversation(['is_sandbox' => true]);
    ChatMessage::create(['chat_conversation_id' => $sandbox->id, 'role' => 'ai', 'content' => 'x', 'feedback' => 'good']);

    $stats = app(ChatAnalyticsService::class)->forSite('main');

    expect($stats['feedback_good'])->toBe(0);
});
