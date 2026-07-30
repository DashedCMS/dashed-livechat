<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedLivechat\Livewire\Frontend\ChatWidget;
use Dashed\DashedLivechat\Tests\Support\Factories;

it('een gewoon chatbericht zet NOOIT visitor_name, ook niet tijdens de naam-stap', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'name')
        ->set('draft', 'maat M graag')   // antwoord op een productvraag, GEEN naam
        ->call('sendMessage');
    expect($c->fresh()->visitor_name)->toBeNull();
});

it('submitContactName zet de naam en wist de stap', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'name')
        ->set('contactDraft', 'Tanika Dekker')
        ->call('submitContactName');
    expect($c->fresh()->visitor_name)->toBe('Tanika Dekker');
});

it('submitContactName weigert een naam die op een e-mail lijkt', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    $t = Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'name')
        ->set('contactDraft', 'jan@example.com')
        ->call('submitContactName');
    expect($c->fresh()->visitor_name)->toBeNull()
        ->and($t->get('contactError'))->not->toBeNull();
});

it('submitContactEmail zet e-mail bij geldig adres en gaat door naar de naam-stap', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    $t = Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'email')
        ->set('contactDraft', 'tanika@hotmail.com')
        ->call('submitContactEmail');
    expect($c->fresh()->visitor_email)->toBe('tanika@hotmail.com')
        ->and($t->get('contactStep'))->toBe('name');
});

it('submitContactEmail weigert een ongeldig adres en blijft op de e-mail-stap', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    $t = Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'email')
        ->set('contactDraft', 'geen-email')
        ->call('submitContactEmail');
    expect($c->fresh()->visitor_email)->toBeNull()
        ->and($t->get('contactStep'))->toBe('email')
        ->and($t->get('contactError'))->not->toBeNull();
});

it('laat "waar kan ik je mee helpen" weg zodra een mens actief is', function () {
    $c = Factories::makeConversation(['mode' => 'human']);
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'name')
        ->set('contactDraft', 'Tanika')
        ->call('submitContactName');
    $last = $c->fresh()->messages()->reorder()->latest('id')->first();
    expect($last->content)->toBe('Dank je, Tanika!')
        ->and($last->content)->not->toContain('Waar kan ik');
});

it('houdt "waar kan ik je mee helpen" wel als de AI actief is', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'name')
        ->set('contactDraft', 'Tanika')
        ->call('submitContactName');
    $last = $c->fresh()->messages()->reorder()->latest('id')->first();
    expect($last->content)->toContain('Waar kan ik');
});

it('rendert een e-mailveld tijdens de e-mail-stap en een naam-veld tijdens de naam-stap', function () {
    $c = Factories::makeConversation(['mode' => 'ai']);
    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('contactStep', 'email')
        ->assertSeeHtml('type="email"')
        ->assertSeeHtml('wire:click="submitContactEmail"')
        ->assertSeeHtml('wire:click="dismissContact"');
});
