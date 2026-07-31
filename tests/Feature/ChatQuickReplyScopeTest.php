<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\ChatQuickReply;

it('heeft de nieuwe shortcut- en owner_id-kolommen', function () {
    expect(Schema::hasColumn('dashed__chat_quick_replies', 'shortcut'))->toBeTrue()
        ->and(Schema::hasColumn('dashed__chat_quick_replies', 'owner_id'))->toBeTrue();
});

it('scopeVisibleTo geeft gedeelde snippets aan iedereen, ook zonder ingelogde user', function () {
    $shared = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Gedeeld', 'content' => '...', 'owner_id' => null]);

    $result = ChatQuickReply::query()->visibleTo(null, 'main')->get();

    expect($result->pluck('id')->all())->toBe([$shared->id]);
});

it('scopeVisibleTo geeft gedeelde + eigen snippets, niet die van een andere user', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $shared = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Gedeeld', 'content' => '...', 'owner_id' => null]);
    $mine = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Van mij', 'content' => '...', 'owner_id' => $me->id]);
    $theirs = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Van een ander', 'content' => '...', 'owner_id' => $other->id]);

    $result = ChatQuickReply::query()->visibleTo($me->id, 'main')->pluck('id')->all();

    expect($result)->toContain($shared->id)
        ->and($result)->toContain($mine->id)
        ->and($result)->not->toContain($theirs->id);
});

it('scopeVisibleTo is site-scoped', function () {
    $me = User::factory()->create();

    $mainSite = ChatQuickReply::create(['site_id' => 'main', 'title' => 'Main', 'content' => '...', 'owner_id' => null]);
    ChatQuickReply::create(['site_id' => 'other-site', 'title' => 'Andere site', 'content' => '...', 'owner_id' => null]);

    $result = ChatQuickReply::query()->visibleTo($me->id, 'main')->pluck('id')->all();

    expect($result)->toBe([$mainSite->id]);
});
