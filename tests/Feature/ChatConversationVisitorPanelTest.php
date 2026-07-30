<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedLivechat\Tests\Support\Factories;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages\ViewChatConversation;

it('toont de bezoeker-sectie met IP, stad en "Nieuwe bezoeker" voor een eerste gesprek', function () {
    $conversation = Factories::makeConversation([
        'ip_hash' => 'HASH-NEW',
        'visitor_ip' => '203.0.113.42',
        'visitor_user_agent' => 'Mozilla/5.0 (Testbrowser)',
        'visitor_referrer' => 'https://google.com/',
        'visitor_country' => 'Netherlands',
        'visitor_city' => 'Rotterdam',
        'started_url' => 'https://example.com/landing',
    ]);

    Livewire::test(ViewChatConversation::class, ['record' => $conversation->id])
        ->assertSee('Bezoeker')
        ->assertSee('203.0.113.42')
        ->assertSee('Rotterdam')
        ->assertSee('Netherlands')
        ->assertSee('https://example.com/landing')
        ->assertSee('https://google.com/')
        ->assertSee('Mozilla/5.0 (Testbrowser)')
        ->assertSee('Nieuwe bezoeker');
});

it('toont de terugkerend-tekst met aantal en laatste datum voor een terugkerende bezoeker', function () {
    $first = Factories::makeConversation(['ip_hash' => 'HASH-RETURN']);
    $first->forceFill(['created_at' => now()->subDays(2)])->save();

    $second = Factories::makeConversation([
        'ip_hash' => 'HASH-RETURN',
        'visitor_ip' => '198.51.100.7',
        'visitor_city' => 'Amsterdam',
        'visitor_country' => 'Netherlands',
    ]);

    Livewire::test(ViewChatConversation::class, ['record' => $second->id])
        ->assertSee('Terugkerend')
        ->assertSee('1 eerder')
        ->assertSee(now()->subDays(2)->format('d-m-Y'));
});
