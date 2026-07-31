<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Models\ChatTrigger;

it('slaat min_page_views op en leest het terug', function () {
    $trigger = ChatTrigger::create([
        'site_id' => 'main',
        'name' => 'Na 3 paginas',
        'is_active' => true,
        'placement' => 'all_pages',
        'trigger_type' => 'time_on_page',
        'trigger_value' => 5,
        'min_page_views' => 3,
        'proactive_message' => 'Kan ik helpen?',
    ]);

    expect($trigger->fresh()->min_page_views)->toBe(3);
});

it('min_page_views mag leeg blijven', function () {
    $trigger = ChatTrigger::create([
        'site_id' => 'main',
        'name' => 'Direct',
        'is_active' => true,
        'placement' => 'all_pages',
        'trigger_type' => 'immediate',
        'proactive_message' => 'Hoi!',
    ]);

    expect($trigger->fresh()->min_page_views)->toBeNull();
});
