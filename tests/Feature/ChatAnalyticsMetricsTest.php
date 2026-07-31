<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\ChatTag;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\ChatAnalyticsService;

function convo(array $attrs = []): ChatConversation
{
    return ChatConversation::create(array_merge([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'active',
        'mode' => 'ai',
    ], $attrs));
}

it('berekent CSAT-metrics uit de rating-kolommen', function () {
    convo(['rating' => 5]);
    convo(['rating' => 4]);
    convo(['rating' => 1]);
    convo(); // niet beoordeeld

    $stats = app(ChatAnalyticsService::class)->forSite('main');

    expect($stats['rating_count'])->toBe(3)
        ->and($stats['rating_avg'])->toBe(3.3) // (5+4+1)/3 = 3.33 → 3.3
        ->and($stats['csat_positive_pct'])->toBe(67); // 2 van 3 ≥ 4
});

it('geeft een tag-verdeling met aantallen', function () {
    $tag = ChatTag::create(['site_id' => 'main', 'name' => 'Verkoop', 'color' => '#16a34a', 'sort' => 0]);
    convo()->tags()->sync([$tag->id]);
    convo()->tags()->sync([$tag->id]);

    $stats = app(ChatAnalyticsService::class)->forSite('main');

    expect($stats['tag_breakdown'])->toHaveCount(1)
        ->and($stats['tag_breakdown'][0]['name'])->toBe('Verkoop')
        ->and($stats['tag_breakdown'][0]['count'])->toBe(2);
});

it('levert 24 uur-buckets voor drukte', function () {
    convo();
    $stats = app(ChatAnalyticsService::class)->forSite('main');

    expect($stats['busy_hours'])->toHaveCount(24)
        ->and(array_sum($stats['busy_hours']))->toBe(1);
});

it('berekent de gemiddelde eerste-reactietijd', function () {
    $conv = convo(['mode' => 'human']);
    ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'visitor', 'content' => 'Hoi', 'is_internal' => false, 'created_at' => now()->subMinutes(4)]);
    ChatMessage::create(['chat_conversation_id' => $conv->id, 'role' => 'human', 'content' => 'Hallo!', 'is_internal' => false, 'created_at' => now()->subMinutes(2)]);

    $stats = app(ChatAnalyticsService::class)->forSite('main');

    expect($stats['avg_response_minutes'])->toBe(2);
});
