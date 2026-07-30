<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Mail\ConversationTranscriptMail;
use Dashed\DashedLivechat\Tests\Support\Factories;

it('transcript-mail gebruikt de site-afzender', function () {
    Customsetting::set('site_from_email', 'shop@lovora.nl', 'main');
    Customsetting::set('site_name', 'Lovora', 'main');

    $c = Factories::makeConversation(['visitor_email' => 'k@klant.nl']);
    $mail = new ConversationTranscriptMail($c, 'agent@lovora.nl');
    $env = $mail->envelope();

    expect($env->from->address)->toBe('shop@lovora.nl')
        ->and($env->from->name)->toBe('Lovora');
});

it('valt terug op de mailer-default als de site-setting leeg is', function () {
    config()->set('mail.from.address', 'default@dashed.nl');
    config()->set('mail.from.name', 'Dashed');

    $c = Factories::makeConversation(['site_id' => 'zonder-setting', 'visitor_email' => 'k@klant.nl']);
    $env = (new ConversationTranscriptMail($c))->envelope();

    expect($env->from->address)->toBe('default@dashed.nl')
        ->and($env->from->name)->toBe('Dashed');
});
