<?php

declare(strict_types=1);

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Route;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\ConversationController;

beforeEach(function () {
    Route::middleware('web')->post('_test/conversations/{conversation}/suggest-reply', [ConversationController::class, 'suggestReply']);
});

function suggestConversation(): ChatConversation
{
    return ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'mode' => 'human',
    ]);
}

function fakeClaude(array $content): void
{
    $provider = Mockery::mock(\Dashed\DashedAi\AiProvider::class);
    $provider->shouldReceive('isConnected')->andReturn(true);
    $provider->shouldReceive('messages')->andReturn(['content' => $content]);
    Ai::shouldReceive('provider')->with('claude')->andReturn($provider);
}

it('geeft een AI-concept-antwoord terug', function () {
    $me = User::factory()->create();
    $conv = suggestConversation();
    ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'visitor', 'content' => 'Waar blijft mijn pakket?', 'is_internal' => false]);

    fakeClaude([['type' => 'text', 'text' => 'Je pakket is onderweg en komt morgen aan.']]);

    $this->actingAs($me)
        ->postJson("_test/conversations/{$conv->id}/suggest-reply")
        ->assertSuccessful()
        ->assertJsonPath('suggestion', 'Je pakket is onderweg en komt morgen aan.');
});

it('geeft een lege suggestie terug zonder bruikbare berichten', function () {
    $me = User::factory()->create();
    $conv = suggestConversation();

    // Geen berichten → ReplySuggester roept de AI niet aan en geeft '' terug.
    $this->actingAs($me)
        ->postJson("_test/conversations/{$conv->id}/suggest-reply")
        ->assertSuccessful()
        ->assertJsonPath('suggestion', '');
});

it('stuurt een payload die op een user-turn eindigt, ook als het gesprek op de AI eindigt', function () {
    $me = User::factory()->create();
    $conv = suggestConversation();
    ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'visitor', 'content' => 'Is dit product leverbaar?', 'is_internal' => false]);
    ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'ai', 'content' => 'Ja hoor, op voorraad!', 'is_internal' => false]);

    // Vang de messages-array die naar Claude gaat.
    $captured = null;
    $provider = Mockery::mock(\Dashed\DashedAi\AiProvider::class);
    $provider->shouldReceive('isConnected')->andReturn(true);
    $provider->shouldReceive('messages')->andReturnUsing(function (array $messages, array $options = []) use (&$captured) {
        $captured = $messages;

        return ['content' => [['type' => 'text', 'text' => 'Zal ik het voor je reserveren?']]];
    });
    Ai::shouldReceive('provider')->with('claude')->andReturn($provider);

    $this->actingAs($me)
        ->postJson("_test/conversations/{$conv->id}/suggest-reply")
        ->assertSuccessful();

    expect($captured)->not->toBeNull();
    expect($captured[array_key_last($captured)]['role'])->toBe('user');
    // Geen twee opeenvolgende assistant-turns en begint met user.
    expect($captured[0]['role'])->toBe('user');
});
