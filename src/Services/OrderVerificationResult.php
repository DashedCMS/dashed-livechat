<?php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedEcommerceCore\Models\Order;

readonly class OrderVerificationResult
{
    public function __construct(public string $status, public ?Order $order = null)
    {
    }
}
