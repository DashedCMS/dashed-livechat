<?php

// src/Enums/ConversationMode.php

namespace Dashed\DashedLivechat\Enums;

enum ConversationMode: string
{
    case Ai = 'ai';
    case WaitingHuman = 'waiting_human';
    case Human = 'human';
}
