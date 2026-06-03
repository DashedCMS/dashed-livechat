<?php

// src/Guardrails/InputGuardResult.php

namespace Dashed\DashedLivechat\Guardrails;

readonly class InputGuardResult
{
    public function __construct(public bool $blocked, public ?string $reason = null)
    {
    }
}
