<?php

// src/Enums/ConversationStatus.php

namespace Dashed\DashedLivechat\Enums;

enum ConversationStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
}
