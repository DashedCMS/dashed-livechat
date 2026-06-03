<?php

// src/Guardrails/InputGuard.php

namespace Dashed\DashedLivechat\Guardrails;

class InputGuard
{
    /** @var string[] */
    protected array $patterns = [
        '/negeer\s+(al\s+)?je\s+instructies/i',
        '/ignore\s+(all\s+)?(your\s+)?instructions/i',
        '/system\s*prompt/i',
        '/systeem\s*prompt/i',
        '/jailbreak/i',
        '/doe\s+alsof\s+je\s+geen\s+regels/i',
    ];

    public function check(string $message): InputGuardResult
    {
        foreach ($this->patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return new InputGuardResult(true, 'prompt_injection');
            }
        }

        return new InputGuardResult(false);
    }
}
