<?php

// src/Enums/TriggerPlacement.php

namespace Dashed\DashedLivechat\Enums;

enum TriggerPlacement: string
{
    case AllPages = 'all_pages';
    case IncludeUrls = 'include_urls';
    case UrlPattern = 'url_pattern';
    case Models = 'models';
}
