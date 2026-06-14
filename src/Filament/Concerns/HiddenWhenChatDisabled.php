<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Filament\Concerns;

use Dashed\DashedLivechat\Support\ChatModule;

/**
 * Verbergt het Filament-menu-item zolang livechat op geen enkele site is
 * ingeschakeld via de instellingen. De pagina/resource blijft wel bereikbaar
 * via directe URL; enkel de navigatie wordt verborgen.
 */
trait HiddenWhenChatDisabled
{
    public static function shouldRegisterNavigation(): bool
    {
        return ChatModule::enabled();
    }
}
