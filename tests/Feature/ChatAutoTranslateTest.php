<?php

declare(strict_types=1);

use Dashed\DashedAi\Facades\Ai;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Route;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Support\MessageTranslator;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\ConversationController;

beforeEach(function () {
    Route::middleware('web')->put('_test/conversations/{conversation}/auto-translate', [ConversationController::class, 'setAutoTranslate']);
});

function translateConversation(): ChatConversation
{
    return ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'mode' => 'human',
        'locale' => 'en',
    ]);
}

function fakeTranslator(string $out, ?int $times = null): void
{
    $provider = Mockery::mock(\Dashed\DashedAi\AiProvider::class);
    $provider->shouldReceive('isConnected')->andReturn(true);
    $expectation = $provider->shouldReceive('messages')->andReturn(['content' => [['type' => 'text', 'text' => $out]]]);
    if ($times !== null) {
        $expectation->times($times);
    }
    Ai::shouldReceive('provider')->with('claude')->andReturn($provider);
}

it('cachet de vertaling en vertaalt niet opnieuw', function () {
    $conv = translateConversation();
    $msg = ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'human', 'content' => 'Je pakket komt morgen.', 'is_internal' => false]);

    fakeTranslator('Your parcel arrives tomorrow.', times: 1);

    $translator = new MessageTranslator();
    $first = $translator->ensureTranslated($msg, 'en');
    $second = $translator->ensureTranslated($msg->fresh(), 'en');

    expect($first)->toBe('Your parcel arrives tomorrow.')
        ->and($second)->toBe('Your parcel arrives tomorrow.')
        ->and($msg->fresh()->translated_content)->toBe('Your parcel arrives tomorrow.')
        ->and($msg->fresh()->source_locale)->toBe('en');
});

it('valt terug op het origineel bij een vertaalfout', function () {
    $conv = translateConversation();
    $msg = ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'human', 'content' => 'Origineel', 'is_internal' => false]);

    // Provider niet verbonden → requireClaude() gooit → best-effort fallback.
    $provider = Mockery::mock(\Dashed\DashedAi\AiProvider::class);
    $provider->shouldReceive('isConnected')->andReturn(false);
    Ai::shouldReceive('provider')->with('claude')->andReturn($provider);

    expect((new MessageTranslator())->ensureTranslated($msg, 'en'))->toBe('Origineel')
        ->and($msg->fresh()->translated_content)->toBeNull();
});

it('zet auto-vertaling aan via het endpoint', function () {
    $me = User::factory()->create();
    $conv = translateConversation();

    $this->actingAs($me)
        ->putJson("_test/conversations/{$conv->id}/auto-translate", ['enabled' => true, 'agent_locale' => 'nl'])
        ->assertSuccessful()
        ->assertJsonPath('auto_translate', true)
        ->assertJsonPath('agent_locale', 'nl');

    expect($conv->fresh()->auto_translate)->toBeTrue();
});
