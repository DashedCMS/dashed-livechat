<?php

declare(strict_types=1);

it('genereert een geldig VAPID-sleutelpaar en toont beide sleutels', function () {
    $this->artisan('chat:generate-web-push-keys')
        ->assertExitCode(0)
        ->expectsOutputToContain('LIVECHAT_WEBPUSH_PUBLIC_KEY=')
        ->expectsOutputToContain('LIVECHAT_WEBPUSH_PRIVATE_KEY=');
});
