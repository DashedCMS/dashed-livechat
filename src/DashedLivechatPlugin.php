<?php

namespace Dashed\DashedLivechat;

use Filament\Panel;
use Filament\Contracts\Plugin;
use Dashed\DashedLivechat\Filament\Pages\ChatDashboard;
use Dashed\DashedLivechat\Filament\Pages\VisitorsLivePage;
use Dashed\DashedLivechat\Filament\Resources\ChatAgentResource;
use Dashed\DashedLivechat\Filament\Resources\ChatTriggerResource;
use Dashed\DashedLivechat\Filament\Resources\ChatLearningResource;
use Dashed\DashedLivechat\Filament\Pages\Settings\ChatSettingsPage;
use Dashed\DashedLivechat\Filament\Resources\ChatQuickReplyResource;
use Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource;

class DashedLivechatPlugin implements Plugin
{
    public function getId(): string
    {
        return 'dashed-livechat';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                ChatAgentResource::class,
                ChatConversationResource::class,
                ChatLearningResource::class,
                ChatQuickReplyResource::class,
                ChatOpeningHourResource::class,
                ChatTriggerResource::class,
            ])
            ->pages([
                ChatSettingsPage::class,
                ChatDashboard::class,
                VisitorsLivePage::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
    }
}
