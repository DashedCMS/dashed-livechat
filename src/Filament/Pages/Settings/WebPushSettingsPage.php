<?php

namespace Dashed\DashedLivechat\Filament\Pages\Settings;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Filament\Notifications\Notification;
use Dashed\DashedLivechat\Models\WebPushPreference;

class WebPushSettingsPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Chat';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Bureaubladmeldingen';

    protected static ?string $title = 'Bureaubladmeldingen';

    protected string $view = 'dashed-livechat::filament.pages.web-push-settings';

    public bool $notifyHandoff = true;

    public bool $notifyMessage = true;

    public bool $notifyNew = false;

    public function mount(): void
    {
        $preference = WebPushPreference::where('user_id', auth()->id())
            ->where('site_id', $this->siteId())
            ->first();

        if ($preference) {
            $this->notifyHandoff = (bool) $preference->notify_handoff;
            $this->notifyMessage = (bool) $preference->notify_message;
            $this->notifyNew = (bool) $preference->notify_new;
        }
    }

    public function savePreferences(): void
    {
        WebPushPreference::updateOrCreate(
            ['user_id' => auth()->id(), 'site_id' => $this->siteId()],
            [
                'notify_handoff' => $this->notifyHandoff,
                'notify_message' => $this->notifyMessage,
                'notify_new' => $this->notifyNew,
            ],
        );

        Notification::make()->title('Voorkeuren opgeslagen')->success()->send();
    }

    public function getVapidPublicKey(): ?string
    {
        return \Dashed\DashedLivechat\Services\WebPushService::publicKeyFor($this->siteId());
    }

    protected function siteId(): string
    {
        return (string) Sites::getActive();
    }
}
