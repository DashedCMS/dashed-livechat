<?php

// src/Filament/Pages/ChatDashboard.php

namespace Dashed\DashedLivechat\Filament\Pages;

use UnitEnum;
use Filament\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Services\ChatAnalyticsService;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;

class ChatDashboard extends Page
{
    use HiddenWhenChatDisabled;

    protected static string|UnitEnum|null $navigationGroup = 'Chat';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Chat Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'dashed-livechat::filament.dashboard';

    public array $stats = [];

    public function mount(): void
    {
        $siteId = Sites::getActive() ?: 'main';
        $this->stats = app(ChatAnalyticsService::class)->forSite($siteId);
    }
}
