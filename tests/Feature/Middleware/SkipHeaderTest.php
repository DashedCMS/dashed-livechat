<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Http\Middleware\InjectChatWidget;
use Dashed\DashedLivechat\Http\Middleware\InjectVisitorPresence;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

function skipHeaderResponse(string $value): Response
{
    return new Response('<html><body>x</body></html>', 200, [
        'Content-Type' => 'text/html',
        InjectChatWidget::SKIP_HEADER => $value,
    ]);
}

it('skips the chat widget and removes the header when set to off', function (string $value) {
    $middleware = (new ReflectionClass(InjectChatWidget::class))->newInstanceWithoutConstructor();
    $response = $middleware->handle(Request::create('/'), fn () => skipHeaderResponse($value));

    expect($response->getContent())->toBe('<html><body>x</body></html>')
        ->and($response->headers->has(InjectChatWidget::SKIP_HEADER))->toBeFalse();
})->with(['off', 'OFF', ' Off ']);

it('skips the presence script and removes the header when set to off', function (string $value) {
    $response = (new InjectVisitorPresence())->handle(Request::create('/'), fn () => skipHeaderResponse($value));

    expect($response->getContent())->toBe('<html><body>x</body></html>')
        ->and($response->headers->has(InjectChatWidget::SKIP_HEADER))->toBeFalse();
})->with(['off', 'OFF', ' Off ']);
