<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Route;
use Dashed\DashedLivechat\Models\ChatTag;
use Dashed\DashedLivechat\Models\ChatNote;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\ConversationController;

/**
 * De mobile-api-middleware (`chat.ability`) bestaat niet in dit standalone
 * package-testbench (zie QuickReplyControllerTest), dus we registreren
 * tijdelijke web-routes rechtstreeks naar de controller-acties.
 */
beforeEach(function () {
    Route::middleware('web')->group(function () {
        Route::get('_test/tags', [ConversationController::class, 'tags']);
        Route::get('_test/conversations', [ConversationController::class, 'index']);
        Route::put('_test/conversations/{conversation}/tags', [ConversationController::class, 'setTags']);
        Route::post('_test/conversations/{conversation}/notes', [ConversationController::class, 'addNote']);
    });
});

function makeConversation(string $site = 'main'): ChatConversation
{
    return ChatConversation::create([
        'site_id' => $site,
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'mode' => 'ai',
    ]);
}

it('geeft alle tags van de actieve site terug', function () {
    $me = User::factory()->create();
    ChatTag::create(['site_id' => 'main', 'name' => 'Verkoop', 'color' => '#16a34a', 'sort' => 0]);
    ChatTag::create(['site_id' => 'main', 'name' => 'Klacht', 'color' => '#dc2626', 'sort' => 1]);
    ChatTag::create(['site_id' => 'other', 'name' => 'Andere site', 'color' => '#000000', 'sort' => 0]);

    $names = collect(
        $this->actingAs($me)->getJson('_test/tags')->assertSuccessful()->json('data')
    )->pluck('name')->all();

    expect($names)->toBe(['Verkoop', 'Klacht']);
});

it('koppelt tags aan een gesprek en negeert tags van een andere site', function () {
    $me = User::factory()->create();
    $conversation = makeConversation();
    $a = ChatTag::create(['site_id' => 'main', 'name' => 'A', 'color' => '#111', 'sort' => 0]);
    $b = ChatTag::create(['site_id' => 'main', 'name' => 'B', 'color' => '#222', 'sort' => 1]);
    $foreign = ChatTag::create(['site_id' => 'other', 'name' => 'X', 'color' => '#333', 'sort' => 0]);

    $this->actingAs($me)
        ->putJson("_test/conversations/{$conversation->id}/tags", ['tag_ids' => [$a->id, $b->id, $foreign->id]])
        ->assertSuccessful();

    expect($conversation->fresh()->tags->pluck('id')->sort()->values()->all())
        ->toBe([$a->id, $b->id]);
});

it('vervangt de bestaande tag-set bij sync', function () {
    $me = User::factory()->create();
    $conversation = makeConversation();
    $a = ChatTag::create(['site_id' => 'main', 'name' => 'A', 'color' => '#111', 'sort' => 0]);
    $b = ChatTag::create(['site_id' => 'main', 'name' => 'B', 'color' => '#222', 'sort' => 1]);
    $conversation->tags()->sync([$a->id]);

    $this->actingAs($me)
        ->putJson("_test/conversations/{$conversation->id}/tags", ['tag_ids' => [$b->id]])
        ->assertSuccessful();

    expect($conversation->fresh()->tags->pluck('id')->all())->toBe([$b->id]);
});

it('voegt een interne notitie toe met de auteur', function () {
    $me = User::factory()->create();
    $conversation = makeConversation();

    $this->actingAs($me)
        ->postJson("_test/conversations/{$conversation->id}/notes", ['body' => '  Klant belde eerder  '])
        ->assertCreated()
        ->assertJsonPath('data.author', $me->name)
        ->assertJsonPath('data.body', 'Klant belde eerder');

    $note = ChatNote::first();
    expect($note->chat_conversation_id)->toBe($conversation->id)
        ->and($note->user_id)->toBe($me->id)
        ->and($note->body)->toBe('Klant belde eerder');
});

it('weigert een lege notitie', function () {
    $me = User::factory()->create();
    $conversation = makeConversation();

    $this->actingAs($me)
        ->postJson("_test/conversations/{$conversation->id}/notes", ['body' => ''])
        ->assertStatus(422);

    expect(ChatNote::count())->toBe(0);
});

it('filtert de gesprekkenlijst op tag_id', function () {
    $me = User::factory()->create();
    $tagged = makeConversation();
    $untagged = makeConversation();
    $tag = ChatTag::create(['site_id' => 'main', 'name' => 'Verkoop', 'color' => '#16a34a', 'sort' => 0]);
    $tagged->tags()->sync([$tag->id]);

    $ids = collect(
        $this->actingAs($me)->getJson("_test/conversations?tag_id={$tag->id}")->assertSuccessful()->json('data')
    )->pluck('id')->all();

    expect($ids)->toContain($tagged->id)->not->toContain($untagged->id);
});
