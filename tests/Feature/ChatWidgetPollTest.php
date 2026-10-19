<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedLivechat\Tests\Support\Factories;
use Dashed\DashedLivechat\Livewire\Frontend\ChatWidget;

it('pollt niet zonder gesprek en met gesloten paneel', function () {
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->assertDontSee('wire:poll', false);
});

it('pollt traag bij een gesprek met gesloten paneel', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->assertSee('wire:poll.10000ms="pollReply"', false);
});

it('pollt snel zodra het paneel open is', function () {
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('open', true)
        ->assertSee('wire:poll.1500ms="pollReply"', false);
});
