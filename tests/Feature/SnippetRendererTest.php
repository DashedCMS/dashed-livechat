<?php

declare(strict_types=1);

use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Support\SnippetRenderer;
use Dashed\DashedLivechat\Tests\Support\Factories;

it('vervangt alle 4 tokens', function () {
    $conversation = Factories::makeConversation([
        'visitor_name' => 'Jan',
        'visitor_email' => 'jan@example.com',
    ]);
    $agent = User::factory()->create(['first_name' => 'Anna', 'last_name' => '']);

    $result = SnippetRenderer::render(
        'Hoi {naam}, dit is {agent} van {shop}. Je e-mail: {email}.',
        $conversation,
        $agent,
        'Mijn Winkel'
    );

    expect($result)->toBe('Hoi Jan, dit is Anna van Mijn Winkel. Je e-mail: jan@example.com.');
});

it('valt terug op "daar" als de bezoekersnaam ontbreekt', function () {
    $conversation = Factories::makeConversation(['visitor_name' => null]);

    $result = SnippetRenderer::render('Hoi {naam}!', $conversation, null, 'Mijn Winkel');

    expect($result)->toBe('Hoi daar!');
});

it('laat een onbekend token letterlijk staan', function () {
    $conversation = Factories::makeConversation(['visitor_name' => 'Jan']);

    $result = SnippetRenderer::render('Hoi {naam}, je {ordernummer} is onderweg.', $conversation, null, 'Mijn Winkel');

    expect($result)->toBe('Hoi Jan, je {ordernummer} is onderweg.');
});

it('is case-insensitief op de tokennaam', function () {
    $conversation = Factories::makeConversation(['visitor_name' => 'Jan']);

    $result = SnippetRenderer::render('Hoi {NAAM} en {Shop}!', $conversation, null, 'Mijn Winkel');

    expect($result)->toBe('Hoi Jan en Mijn Winkel!');
});

it('geeft lege string voor {agent} en {email} als die onbekend zijn', function () {
    $conversation = Factories::makeConversation(['visitor_name' => 'Jan', 'visitor_email' => null]);

    $result = SnippetRenderer::render('Agent: [{agent}] Email: [{email}]', $conversation, null, 'Mijn Winkel');

    expect($result)->toBe('Agent: [] Email: []');
});

it('werkt zonder conversation (null)', function () {
    $result = SnippetRenderer::render('Hoi {naam}, welkom bij {shop}.', null, null, 'Mijn Winkel');

    expect($result)->toBe('Hoi daar, welkom bij Mijn Winkel.');
});
