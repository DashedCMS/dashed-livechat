<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\ChatQuickReply;
use Dashed\DashedLivechat\Tests\Support\Factories;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages\ViewChatConversation;

function makeAgent(): User
{
    return User::factory()->create(['role' => 'superadmin']);
}

it('quickReplies-property toont gedeelde + eigen snippets, niet die van een ander', function () {
    $me = makeAgent();
    $other = makeAgent();
    $conversation = Factories::makeConversation();

    $shared = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Gedeeld', 'content' => 'Hoi', 'owner_id' => null, 'sort' => 0]);
    $mine = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Van mij', 'content' => 'Hoi', 'owner_id' => $me->id, 'sort' => 1]);
    ChatQuickReply::create(['site_id' => 'main', 'title' => 'Van een ander', 'content' => 'Hoi', 'owner_id' => $other->id, 'sort' => 2]);

    $this->actingAs($me);
    $page = new ViewChatConversation();
    $page->mount($conversation->id);

    $ids = $page->quickReplies->pluck('id')->all();
    expect($ids)->toContain($shared->id)
        ->and($ids)->toContain($mine->id)
        ->and($ids)->toHaveCount(2);
});

it('insertQuickReply vervangt het conceptantwoord door de resolved snippet-inhoud', function () {
    $me = makeAgent();
    $conversation = Factories::makeConversation(['visitor_name' => 'Jan']);

    $quickReply = ChatQuickReply::create([
        'site_id' => 'main',
        'title' => 'Verzending',
        'content' => 'Hoi {naam}, we versturen morgen vanuit {shop}.',
        'owner_id' => null,
        'sort' => 0,
    ]);

    $this->actingAs($me);
    $page = new ViewChatConversation();
    $page->mount($conversation->id);
    $page->insertQuickReply($quickReply->id);

    expect($page->reply)->toBe('Hoi Jan, we versturen morgen vanuit Main.');
});

it('insertQuickReply doet niets voor een snippet van een andere agent', function () {
    $me = makeAgent();
    $other = makeAgent();
    $conversation = Factories::makeConversation();

    $theirs = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Van een ander', 'content' => 'Geheim', 'owner_id' => $other->id, 'sort' => 0]);

    $this->actingAs($me);
    $page = new ViewChatConversation();
    $page->mount($conversation->id);
    $page->reply = 'origineel';
    $page->insertQuickReply($theirs->id);

    expect($page->reply)->toBe('origineel');
});

it('expandShortcut vervangt de tekst wanneer /shortcut gevolgd door een spatie matcht', function () {
    $me = makeAgent();
    $conversation = Factories::makeConversation(['visitor_name' => 'Jan']);

    ChatQuickReply::create([
        'site_id' => 'main',
        'title' => 'Verzending',
        'shortcut' => 'verzending',
        'content' => 'Hoi {naam}, we versturen morgen.',
        'owner_id' => null,
        'sort' => 0,
    ]);

    $this->actingAs($me);
    $page = new ViewChatConversation();
    $page->mount($conversation->id);
    $page->expandShortcut('/verzending ');

    expect($page->reply)->toBe('Hoi Jan, we versturen morgen.');
});

it('expandShortcut is case-insensitief op de shortcut-naam', function () {
    $me = makeAgent();
    $conversation = Factories::makeConversation(['visitor_name' => 'Jan']);

    ChatQuickReply::create([
        'site_id' => 'main',
        'title' => 'Verzending',
        'shortcut' => 'Verzending',
        'content' => 'Klaar!',
        'owner_id' => null,
        'sort' => 0,
    ]);

    $this->actingAs($me);
    $page = new ViewChatConversation();
    $page->mount($conversation->id);
    $page->expandShortcut('/VERZENDING ');

    expect($page->reply)->toBe('Klaar!');
});

it('expandShortcut laat de tekst ongemoeid als er geen match is', function () {
    $me = makeAgent();
    $conversation = Factories::makeConversation();

    $this->actingAs($me);
    $page = new ViewChatConversation();
    $page->mount($conversation->id);
    $page->reply = 'gewone tekst zonder shortcut';
    $page->expandShortcut('gewone tekst zonder shortcut');

    expect($page->reply)->toBe('gewone tekst zonder shortcut');
});

it('toont de "Snelle antwoorden"-knop in de reply-box zodra er snippets zijn (regressie op bestaande pagina)', function () {
    $me = makeAgent();
    $conversation = Factories::makeConversation();
    $conversation->forceFill(['mode' => 'human'])->save();

    ChatQuickReply::create(['site_id' => 'main', 'title' => 'Verzending', 'content' => 'Hoi', 'owner_id' => null, 'sort' => 0]);

    Livewire::actingAs($me)
        ->test(ViewChatConversation::class, ['record' => $conversation->id])
        ->assertSee('Snelle antwoorden');
});
