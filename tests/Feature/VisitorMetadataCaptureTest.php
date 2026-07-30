<?php

declare(strict_types=1);

use Livewire\Livewire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Guardrails\InputGuard;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Livewire\Frontend\ChatWidget;
use Dashed\DashedLivechat\Services\ConversationManager;

/**
 * Livewire's testing-harness stuurt voor élke subsequent call (->set()/->call())
 * altijd dezelfde vaste request-headers mee (alleen `X-Livewire: true`, zie
 * Livewire\Features\SupportTesting\SubsequentRender::makeSubsequentRequest());
 * custom headers/IP zijn dus niet via ->withServerVariables()/Livewire::withHeaders()
 * te injecteren voor een Livewire-actie-call. Om toch een realistische bezoeker-
 * request (IP/user agent/referrer) te simuleren voor sendMessage() zelf, binden we
 * een eigen Request-instance in de container en roepen we de methode direct aan op
 * de gehydrateerde component-instance (dezelfde weg als Livewire's eigen dispatch,
 * maar zonder de hardcoded testing-headers te overschrijven).
 */
it('snapshot IP, user agent, referrer en geo bij het aanmaken van een gesprek', function () {
    ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Testbot', 'is_active' => true]);

    // geo-lookup faket (VisitorGeo gebruikt http://ip-api.com); voorkom echte call.
    Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'country' => 'Netherlands', 'city' => 'Amsterdam'], 200)]);

    $t = Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('draft', 'hallo');

    app()->instance('request', Request::create('/', 'POST', [], [], [], [
        'REMOTE_ADDR' => '8.8.8.8',
        'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        'HTTP_REFERER' => 'https://google.com/',
    ]));

    $t->instance()->sendMessage(app(ConversationManager::class), app(InputGuard::class));

    $c = ChatConversation::where('site_id', 'main')->latest('id')->first();
    expect($c)->not->toBeNull()
        ->and($c->visitor_ip)->toBe('8.8.8.8')
        ->and($c->visitor_user_agent)->toBe('TestBrowser/1.0')
        ->and($c->visitor_referrer)->toBe('https://google.com/')
        ->and($c->visitor_country)->toBe('Netherlands')
        ->and($c->visitor_city)->toBe('Amsterdam');
});

it('vult de snapshot NIET opnieuw bij een bestaand gesprek (alleen bij aanmaken)', function () {
    ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Testbot', 'is_active' => true]);
    Http::fake(['ip-api.com/*' => Http::response(['status' => 'success', 'country' => 'Netherlands', 'city' => 'Amsterdam'], 200)]);

    $c = \Dashed\DashedLivechat\Tests\Support\Factories::makeConversation([
        'mode' => 'ai',
        'visitor_ip' => '203.0.113.9',
        'visitor_user_agent' => 'OudeBrowser/1.0',
        'visitor_referrer' => 'https://oude-referrer.example/',
        'visitor_country' => 'Belgium',
        'visitor_city' => 'Brussel',
    ]);

    $t = Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('publicToken', $c->public_token)
        ->set('draft', 'nog een bericht');

    app()->instance('request', Request::create('/', 'POST', [], [], [], [
        'REMOTE_ADDR' => '8.8.8.8',
        'HTTP_USER_AGENT' => 'TestBrowser/1.0',
        'HTTP_REFERER' => 'https://google.com/',
    ]));

    $t->instance()->sendMessage(app(ConversationManager::class), app(InputGuard::class));

    expect($c->fresh()->visitor_ip)->toBe('203.0.113.9')
        ->and($c->fresh()->visitor_user_agent)->toBe('OudeBrowser/1.0')
        ->and($c->fresh()->visitor_referrer)->toBe('https://oude-referrer.example/')
        ->and($c->fresh()->visitor_country)->toBe('Belgium')
        ->and($c->fresh()->visitor_city)->toBe('Brussel');
});
