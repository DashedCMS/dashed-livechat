<?php

use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Tests\Support\Factories;

it('markeert een tweede gesprek van dezelfde ip_hash als terugkerend', function () {
    $first = Factories::makeConversation(['ip_hash' => 'HASH-A']);
    $first->forceFill(['created_at' => now()->subDays(3)])->save();
    $second = Factories::makeConversation(['ip_hash' => 'HASH-A']);

    $info = $second->returningVisitorInfo();
    expect($info['is_returning'])->toBeTrue()
        ->and($info['count'])->toBe(1)
        ->and($info['last_at']->toDateString())->toBe(now()->subDays(3)->toDateString());
});

it('een eerste gesprek is nieuw; andere ip_hash of site telt niet mee', function () {
    Factories::makeConversation(['ip_hash' => 'HASH-B', 'site_id' => 'main']);
    Factories::makeConversation(['ip_hash' => 'HASH-A', 'site_id' => 'other']);
    $c = Factories::makeConversation(['ip_hash' => 'HASH-A', 'site_id' => 'main']);
    expect($c->returningVisitorInfo()['is_returning'])->toBeFalse()
        ->and($c->returningVisitorInfo()['count'])->toBe(0);
});
