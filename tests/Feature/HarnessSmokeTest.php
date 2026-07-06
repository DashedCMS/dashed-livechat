<?php

declare(strict_types=1);

use Dashed\DashedLivechat\Tests\Support\Factories;

it('boot de package-testomgeving en maakt testdata', function () {
    $data = Factories::makeOrderWithProduct();
    expect($data['order']->exists)->toBeTrue()
        ->and($data['product']->stock)->toBe(5);
});
