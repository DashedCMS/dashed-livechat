<?php

// src/Enums/MessageRole.php

namespace Dashed\DashedLivechat\Enums;

enum MessageRole: string
{
    case Visitor = 'visitor';
    case Ai = 'ai';
    case Human = 'human';
    case System = 'system';
}
