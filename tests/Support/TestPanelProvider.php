<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Tests\Support;

use Filament\Panel;
use Filament\PanelProvider;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource;

/**
 * Minimaal Filament-panel voor de package-testbench. Filament-pagina's
 * (`<x-filament::page>` en soortgelijke Blade-componenten) verwachten dat er
 * een standaardpaneel geregistreerd is; zonder dit panel gooit elke
 * `Livewire::test()` van een Filament-pagina een NoDefaultPanelSetException.
 *
 * ChatConversationResource wordt hier expliciet geregistreerd omdat de
 * View-page (ViewChatConversation) via `ChatConversationResource::getUrl('index')`
 * (o.a. voor de verwijder-actie) een gerouteerd panel-resource verwacht; zonder
 * registratie gooit dat een RouteNotFoundException zodra de pagina rendert.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->resources([
                ChatConversationResource::class,
            ]);
    }
}
