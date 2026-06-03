<?php

// src/Enums/ChatEventType.php

namespace Dashed\DashedLivechat\Enums;

enum ChatEventType: string
{
    case HandoffRequested = 'handoff_requested';
    case HandoffTaken = 'handoff_taken';
    case HandoffReleased = 'handoff_released';
    case OrderVerifyFailed = 'order_verify_failed';
    case OrderVerifyBlocked = 'order_verify_blocked';
    case GuardrailBlock = 'guardrail_block';
}
