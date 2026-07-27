<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedCore\Models\User;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Services\WebPushService;
use Dashed\DashedLivechat\Models\WebPushSubscription;
use Dashed\DashedLivechat\Filament\Pages\Settings\WebPushKeyConfigPage;

function activeSite(): string
{
    return (string) Sites::getActive();
}

it('slaat sleutels op met versleutelde private key', function () {
    $site = activeSite();
    $user = User::factory()->create(['role' => 'superadmin']);

    Livewire::actingAs($user)
        ->test(WebPushKeyConfigPage::class)
        ->set("data.web_push_public_key_{$site}", 'pub-123')
        ->set("data.web_push_private_key_{$site}", 'priv-123')
        ->set("data.web_push_subject_{$site}", 'mailto:test@example.com')
        ->call('submit');

    expect(Customsetting::get('web_push_public_key', $site))->toBe('pub-123')
        ->and(Customsetting::get('web_push_subject', $site))->toBe('mailto:test@example.com')
        ->and(Crypt::decryptString((string) Customsetting::get('web_push_private_key', $site)))->toBe('priv-123')
        ->and(WebPushService::privateKeyFor($site))->toBe('priv-123');
});

it('laat een leeg private-veld de bestaande sleutel ongemoeid', function () {
    $site = activeSite();
    Customsetting::set('web_push_private_key', Crypt::encryptString('bestaande-priv'), $site);
    $user = User::factory()->create(['role' => 'superadmin']);

    Livewire::actingAs($user)
        ->test(WebPushKeyConfigPage::class)
        ->set("data.web_push_public_key_{$site}", 'pub-xyz')
        ->set("data.web_push_private_key_{$site}", '')
        ->call('submit');

    expect(WebPushService::privateKeyFor($site))->toBe('bestaande-priv');
});

it('ruimt subscriptions van de site op als de publieke sleutel wijzigt', function () {
    $site = activeSite();
    Customsetting::set('web_push_public_key', 'oude-pub', $site);
    WebPushSubscription::create([
        'user_id' => 1, 'site_id' => $site,
        'endpoint' => 'https://push.example.com/x', 'endpoint_hash' => hash('sha256', 'https://push.example.com/x'),
        'public_key' => 'p', 'auth_token' => 'a', 'content_encoding' => 'aes128gcm',
    ]);
    $user = User::factory()->create(['role' => 'superadmin']);

    Livewire::actingAs($user)
        ->test(WebPushKeyConfigPage::class)
        ->set("data.web_push_public_key_{$site}", 'nieuwe-pub')
        ->call('submit');

    expect(WebPushSubscription::where('site_id', $site)->count())->toBe(0);
});

it('genereert een sleutelpaar en vult de velden', function () {
    $site = activeSite();
    $user = User::factory()->create(['role' => 'superadmin']);

    $component = Livewire::actingAs($user)
        ->test(WebPushKeyConfigPage::class)
        ->call('generateKeys', $site);

    $component->assertSet("data.web_push_public_key_{$site}", fn ($v) => is_string($v) && strlen($v) > 0)
        ->assertSet("data.web_push_private_key_{$site}", fn ($v) => is_string($v) && strlen($v) > 0);
});
