<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Services\WebPushService;

beforeEach(function () {
    config()->set('dashed-livechat.web_push.public_key', 'env-public');
    config()->set('dashed-livechat.web_push.private_key', 'env-private');
    config()->set('dashed-livechat.web_push.subject', 'mailto:env@example.com');
});

it('valt terug op config als er niets in de DB staat', function () {
    expect(WebPushService::publicKeyFor('main'))->toBe('env-public')
        ->and(WebPushService::privateKeyFor('main'))->toBe('env-private')
        ->and(WebPushService::subjectFor('main'))->toBe('mailto:env@example.com');
});

it('laat de DB-waarde winnen van config en ontsleutelt de private key', function () {
    Customsetting::set('web_push_public_key', 'db-public', 'main');
    Customsetting::set('web_push_private_key', Crypt::encryptString('db-private'), 'main');
    Customsetting::set('web_push_subject', 'mailto:db@example.com', 'main');

    expect(WebPushService::publicKeyFor('main'))->toBe('db-public')
        ->and(WebPushService::privateKeyFor('main'))->toBe('db-private')
        ->and(WebPushService::subjectFor('main'))->toBe('mailto:db@example.com');
});

it('geeft null voor een corrupte private key i.p.v. te crashen', function () {
    Customsetting::set('web_push_public_key', 'db-public', 'main');
    Customsetting::set('web_push_private_key', 'geen-geldige-cipher', 'main');

    expect(WebPushService::privateKeyFor('main'))->toBeNull();
});

it('configured(siteId) is true met beide sleutels en false zonder', function () {
    expect(app(WebPushService::class)->configured('main'))->toBeTrue();

    config()->set('dashed-livechat.web_push.private_key', null);
    expect(app(WebPushService::class)->configured('main'))->toBeFalse();
});

it('valt terug op config voor beide sleutels als alleen de publieke DB-sleutel gezet is', function () {
    Customsetting::set('web_push_public_key', 'db-public', 'main');

    expect(WebPushService::publicKeyFor('main'))->toBe('env-public')
        ->and(WebPushService::privateKeyFor('main'))->toBe('env-private');
});

it('valt terug op config voor beide sleutels als alleen de private DB-sleutel gezet is', function () {
    Customsetting::set('web_push_private_key', Crypt::encryptString('db-private'), 'main');

    expect(WebPushService::publicKeyFor('main'))->toBe('env-public')
        ->and(WebPushService::privateKeyFor('main'))->toBe('env-private');
});
