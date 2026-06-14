<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Support;

use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Centrale check of de livechat-module ergens actief is. Wordt gebruikt om de
 * Filament-menu-items te verbergen zolang livechat op geen enkele site is
 * ingeschakeld via de instellingen (chat_enabled).
 */
class ChatModule
{
    public static function enabled(): bool
    {
        foreach (Sites::getSites() as $site) {
            if ((bool) Customsetting::get('chat_enabled', $site['id'])) {
                return true;
            }
        }

        return false;
    }
}
