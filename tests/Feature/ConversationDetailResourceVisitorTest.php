<?php

use Dashed\DashedLivechat\Tests\Support\Factories;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\ConversationResource;
use Dashed\DashedLivechat\Http\Resources\Api\Mobile\ConversationDetailResource;

it('bevat een visitor-blok met de metadata', function () {
    $c = Factories::makeConversation([
        'ip_hash' => 'HASH-X',
        'visitor_ip' => '1.2.3.4',
        'visitor_user_agent' => 'TestBrowser/1.0',
        'visitor_referrer' => 'https://google.com/',
        'visitor_city' => 'Amsterdam',
        'visitor_country' => 'Netherlands',
        'started_url' => 'https://shop.nl/product',
    ]);
    $arr = (new ConversationDetailResource($c))->toArray(request());
    expect($arr['visitor'])->toMatchArray([
        'ip' => '1.2.3.4',
        'user_agent' => 'TestBrowser/1.0',
        'referrer' => 'https://google.com/',
        'city' => 'Amsterdam',
        'country' => 'Netherlands',
        'started_url' => 'https://shop.nl/product',
        'is_returning' => false,
        'previous_count' => 0,
    ]);
});

it('laat het visitor-blok weg uit de lijst-resource (voorkomt N+1)', function () {
    $c = Factories::makeConversation([
        'ip_hash' => 'HASH-X',
        'visitor_ip' => '1.2.3.4',
    ]);
    $arr = (new ConversationResource($c))->toArray(request());
    expect($arr)->not->toHaveKey('visitor');
});
