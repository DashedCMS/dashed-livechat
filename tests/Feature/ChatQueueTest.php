<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Services\ChatQueue;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\OpeningHoursService;

function waitingConversation(string $site = 'main', ?int $assigned = null): ChatConversation
{
    return ChatConversation::create([
        'site_id' => $site,
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'mode' => 'waiting_human',
        'assigned_agent_id' => $assigned,
    ]);
}

function queue(bool $open = true): ChatQueue
{
    $hours = Mockery::mock(OpeningHoursService::class);
    $hours->shouldReceive('isOpen')->andReturn($open);

    return new ChatQueue($hours);
}

it('telt de wachtrij-positie op (ouder vooraan, site-gescoped)', function () {
    $first = waitingConversation();
    $second = waitingConversation();
    $third = waitingConversation();
    waitingConversation('other'); // andere site telt niet mee

    $q = queue();
    expect($q->positionOf($first))->toBe(1)
        ->and($q->positionOf($second))->toBe(2)
        ->and($q->positionOf($third))->toBe(3);
});

it('geeft positie 0 voor een toegewezen of niet-wachtend gesprek', function () {
    $assigned = waitingConversation('main', 42);
    $ai = ChatConversation::create(['site_id' => 'main', 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'status' => 'active', 'mode' => 'ai']);

    $q = queue();
    expect($q->positionOf($assigned))->toBe(0)
        ->and($q->positionOf($ai))->toBe(0);
});

it('schat de wachttijd als positie × afhandeltijd binnen kantooruren', function () {
    config()->set('dashed-livechat.avg_handle_seconds', 180);
    $first = waitingConversation();
    $second = waitingConversation();

    $q = queue(open: true);
    // positie 2 × 180s = 360s = 6 min
    expect($q->estimatedWaitMinutes($second))->toBe(6);
});

it('geeft geen wachttijd buiten kantooruren', function () {
    $conv = waitingConversation();

    $q = queue(open: false);
    expect($q->estimatedWaitMinutes($conv))->toBeNull()
        ->and($q->isWithinOfficeHours('main'))->toBeFalse();
});
