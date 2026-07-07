<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\VisitorSession;
use Dashed\DashedLivechat\Tests\Support\Factories;

it('leidt bezoeker-aanwezigheid af uit visitor_last_active_at', function () {
    expect(Factories::makeConversation(['visitor_last_active_at' => now()])->visitorPresence())->toBe('active')
        ->and(Factories::makeConversation(['visitor_last_active_at' => now()->subSeconds(90)])->visitorPresence())->toBe('idle')
        ->and(Factories::makeConversation(['visitor_last_active_at' => now()->subSeconds(600)])->visitorPresence())->toBe('away')
        ->and(Factories::makeConversation(['visitor_last_active_at' => null])->visitorPresence())->toBe('away');
});

it('gebruikt de presence-beacon om "op de site, tab weg" (idle) te onderscheiden van "weg" (away)', function () {
    // Widget-poll is oud (>away-drempel), maar de site-brede beacon pingt nog:
    // bezoeker is nog op de site met de tab op de achtergrond → idle.
    $token = 'beacon-token-live';
    VisitorSession::create([
        'site_id' => 'main',
        'token' => $token,
        'last_seen_at' => now()->subSeconds(20),
    ]);
    $live = Factories::makeConversation([
        'visitor_last_active_at' => now()->subSeconds(600),
        'visitor_session_token' => $token,
    ]);
    expect($live->visitorPresence())->toBe('idle');

    // Ook zonder ooit een widget-poll telt een verse beacon als idle.
    $tokenOnly = 'beacon-token-only';
    VisitorSession::create([
        'site_id' => 'main',
        'token' => $tokenOnly,
        'last_seen_at' => now()->subSeconds(10),
    ]);
    $beaconOnly = Factories::makeConversation([
        'visitor_last_active_at' => null,
        'visitor_session_token' => $tokenOnly,
    ]);
    expect($beaconOnly->visitorPresence())->toBe('idle');

    // Beacon óók oud → bezoeker is echt van de site af → away.
    $staleToken = 'beacon-token-stale';
    VisitorSession::create([
        'site_id' => 'main',
        'token' => $staleToken,
        'last_seen_at' => now()->subSeconds(600),
    ]);
    $gone = Factories::makeConversation([
        'visitor_last_active_at' => now()->subSeconds(600),
        'visitor_session_token' => $staleToken,
    ]);
    expect($gone->visitorPresence())->toBe('away');
});
