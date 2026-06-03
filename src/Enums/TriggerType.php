<?php

// src/Enums/TriggerType.php

namespace Dashed\DashedLivechat\Enums;

enum TriggerType: string
{
    case None = 'none';
    case Immediate = 'immediate';
    case TimeOnPage = 'time_on_page';
    case ScrollDepth = 'scroll_depth';
    case ExitIntent = 'exit_intent';
}
