<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Models\ChatUnansweredQuestion;

function unansweredConversation(): ChatConversation
{
    return ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'mode' => 'ai',
    ]);
}

it('legt de laatste bezoekersvraag vast bij een handoff', function () {
    $conv = unansweredConversation();
    ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'visitor', 'content' => 'Eerste vraag', 'is_internal' => false]);
    ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'visitor', 'content' => 'Kan ik ruilen na 60 dagen?', 'is_internal' => false]);

    ChatUnansweredQuestion::capture($conv, 'handoff');

    $row = ChatUnansweredQuestion::first();
    expect($row->question)->toBe('Kan ik ruilen na 60 dagen?')
        ->and($row->reason)->toBe('handoff')
        ->and($row->status)->toBe('open')
        ->and($row->site_id)->toBe('main');
});

it('gebruikt de expliciet meegegeven vraag', function () {
    $conv = unansweredConversation();

    ChatUnansweredQuestion::capture($conv, 'negative_feedback', 'Waarom duurt verzending zo lang?');

    expect(ChatUnansweredQuestion::first()->question)->toBe('Waarom duurt verzending zo lang?');
});

it('legt niets vast zonder vraag of bezoekersbericht', function () {
    $conv = unansweredConversation();

    ChatUnansweredQuestion::capture($conv, 'handoff');

    expect(ChatUnansweredQuestion::count())->toBe(0);
});
