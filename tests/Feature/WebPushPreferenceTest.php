<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\WebPushPreference;

it('past de standaarden toe als er geen voorkeur-rij is', function () {
    expect(WebPushPreference::enabledFor(1, 'main', 'handoff'))->toBeTrue()
        ->and(WebPushPreference::enabledFor(1, 'main', 'message'))->toBeTrue()
        ->and(WebPushPreference::enabledFor(1, 'main', 'new'))->toBeFalse();
});

it('respecteert een opgeslagen voorkeur-rij', function () {
    WebPushPreference::create([
        'user_id' => 7,
        'site_id' => 'main',
        'notify_handoff' => false,
        'notify_message' => true,
        'notify_new' => true,
    ]);

    expect(WebPushPreference::enabledFor(7, 'main', 'handoff'))->toBeFalse()
        ->and(WebPushPreference::enabledFor(7, 'main', 'message'))->toBeTrue()
        ->and(WebPushPreference::enabledFor(7, 'main', 'new'))->toBeTrue();
});

it('geeft false voor een onbekend type', function () {
    expect(WebPushPreference::enabledFor(1, 'main', 'onzin'))->toBeFalse();
});
