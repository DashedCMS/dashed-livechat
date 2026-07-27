<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\WebPushPreference;
use Dashed\DashedLivechat\Filament\Pages\Settings\WebPushSettingsPage;

it('laadt de standaardvoorkeuren voor een gebruiker zonder rij', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WebPushSettingsPage::class)
        ->assertSet('notifyHandoff', true)
        ->assertSet('notifyMessage', true)
        ->assertSet('notifyNew', false);
});

it('slaat gewijzigde voorkeuren op naar de preferences-tabel', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(WebPushSettingsPage::class)
        ->set('notifyNew', true)
        ->set('notifyMessage', false)
        ->call('savePreferences');

    $preference = WebPushPreference::where('user_id', $user->id)->first();
    expect($preference)->not->toBeNull()
        ->and($preference->notify_new)->toBeTrue()
        ->and($preference->notify_message)->toBeFalse()
        ->and($preference->notify_handoff)->toBeTrue();
});

it('gebruikt de per-site publieke sleutel uit de database voor de opt-in', function () {
    config()->set('dashed-livechat.web_push.public_key', null);
    \Dashed\DashedCore\Models\Customsetting::set('web_push_public_key', 'db-site-public', \Dashed\DashedCore\Classes\Sites::getActive());

    $user = User::factory()->create();

    $key = Livewire::actingAs($user)
        ->test(WebPushSettingsPage::class)
        ->instance()
        ->getVapidPublicKey();

    expect($key)->toBe('db-site-public');
});
