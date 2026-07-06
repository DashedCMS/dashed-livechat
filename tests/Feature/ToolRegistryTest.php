<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Ai\ToolRegistry;

it('kent de nieuwe tools', function () {
    $names = app(ToolRegistry::class)->toolNames();
    expect($names)->toContain('getStockAndDelivery')->toContain('startReturn');
});
