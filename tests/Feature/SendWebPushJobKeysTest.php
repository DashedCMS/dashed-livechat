<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Services\WebPushService;

it('gebruikt de per-site sleutels van de subscription (geen config-fallback als DB gevuld is)', function () {
    // Geen env-sleutels; alleen DB voor site 'main'.
    config()->set('dashed-livechat.web_push.public_key', null);
    config()->set('dashed-livechat.web_push.private_key', null);

    Customsetting::set('web_push_public_key', 'site-public', 'main');
    Customsetting::set('web_push_private_key', Crypt::encryptString('site-private'), 'main');

    expect(WebPushService::privateKeyFor('main'))->toBe('site-private');

    // Sanity: een subscription zonder geconfigureerde site levert geen private key.
    expect(WebPushService::privateKeyFor('leeg'))->toBeNull();
});
