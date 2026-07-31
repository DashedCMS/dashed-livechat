<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\ChatConversation;

it('slaat rating, toelichting en rated_at op', function () {
    $conv = ChatConversation::create([
        'site_id' => 'main',
        'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'closed',
        'mode' => 'ai',
    ]);

    $conv->forceFill(['rating' => 5, 'rating_comment' => 'Top geholpen', 'rated_at' => now()])->save();

    $fresh = $conv->fresh();
    expect($fresh->rating)->toBe(5)
        ->and($fresh->rating_comment)->toBe('Top geholpen')
        ->and($fresh->rated_at)->not->toBeNull();
});

it('filtert positieve en negatieve beoordelingen', function () {
    $mk = fn (int $rating) => ChatConversation::create([
        'site_id' => 'main', 'public_token' => (string) \Illuminate\Support\Str::uuid(),
        'status' => 'closed', 'mode' => 'ai', 'rating' => $rating,
    ]);
    $good = $mk(5);
    $bad = $mk(1);

    $positive = ChatConversation::where('rating', '>=', 4)->pluck('id')->all();
    $negative = ChatConversation::where('rating', '<=', 2)->pluck('id')->all();

    expect($positive)->toContain($good->id)->not->toContain($bad->id)
        ->and($negative)->toContain($bad->id)->not->toContain($good->id);
});
