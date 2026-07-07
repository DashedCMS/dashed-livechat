<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Tests\Support\Factories;

it('leidt bezoeker-aanwezigheid af uit visitor_last_active_at', function () {
    expect(Factories::makeConversation(['visitor_last_active_at' => now()])->visitorPresence())->toBe('active')
        ->and(Factories::makeConversation(['visitor_last_active_at' => now()->subSeconds(90)])->visitorPresence())->toBe('idle')
        ->and(Factories::makeConversation(['visitor_last_active_at' => now()->subSeconds(600)])->visitorPresence())->toBe('away')
        ->and(Factories::makeConversation(['visitor_last_active_at' => null])->visitorPresence())->toBe('away');
});
