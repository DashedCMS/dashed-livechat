<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Route;
use Dashed\DashedLivechat\Models\ChatQuickReply;
use Dashed\DashedLivechat\Http\Controllers\Api\V1\QuickReplyController;

/**
 * `dashed/dashed-mobile-api` is geen (dev-)dependency van dit package (zie
 * composer.json), dus de echte route (`GET quick-replies`, met
 * `chat.ability:chat.read`-middleware) wordt in dit standalone
 * package-testbench nooit geregistreerd — `class_exists(MobileApiRegistry::class)`
 * is hier altijd false. We registreren daarom hier een tijdelijke test-route
 * die rechtstreeks naar dezelfde controller-actie wijst, zodat controller +
 * resource + scope end-to-end getest worden zonder de mobile-api-middleware
 * (die alleen in de host-app/mobile-api-package zelf getest kan worden).
 */
beforeEach(function () {
    Route::middleware('web')->get('_test/quick-replies', [QuickReplyController::class, 'index']);
});

it('geeft shortcut en scope mee in de response', function () {
    $me = User::factory()->create();

    ChatQuickReply::create([
        'site_id' => 'main',
        'title' => 'Verzending',
        'content' => 'We versturen morgen.',
        'shortcut' => 'verzending',
        'owner_id' => null,
        'sort' => 0,
    ]);
    ChatQuickReply::create([
        'site_id' => 'main',
        'title' => 'Persoonlijk',
        'content' => 'Mijn eigen antwoord.',
        'shortcut' => 'mijnantwoord',
        'owner_id' => $me->id,
        'sort' => 1,
    ]);

    $response = $this->actingAs($me)->getJson('_test/quick-replies')->assertSuccessful();

    $response->assertJsonFragment(['shortcut' => 'verzending', 'scope' => 'shared'])
        ->assertJsonFragment(['shortcut' => 'mijnantwoord', 'scope' => 'personal']);
});

it('verbergt de persoonlijke snippet van een andere user', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();

    $shared = ChatQuickReply::create([
        'site_id' => 'main', 'title' => 'Gedeeld', 'content' => '...', 'owner_id' => null, 'sort' => 0,
    ]);
    ChatQuickReply::create([
        'site_id' => 'main', 'title' => 'Van een ander', 'content' => '...', 'owner_id' => $other->id, 'sort' => 1,
    ]);

    $response = $this->actingAs($me)->getJson('_test/quick-replies')->assertSuccessful();

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$shared->id]);
});
