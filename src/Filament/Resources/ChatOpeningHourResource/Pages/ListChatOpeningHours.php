<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource\Pages;

use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Models\ChatOpeningHour;
use Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource;

class ListChatOpeningHours extends ListRecords
{
    protected static string $resource = ChatOpeningHourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('standaard')
                ->label('Standaard openingstijden')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Standaard openingstijden instellen')
                ->modalDescription('Dit zet de wekelijkse openingstijden op maandag t/m vrijdag 08:00-17:00 en zaterdag 08:00-12:00. Bestaande wekelijkse regels worden vervangen (uitzonderingen op datum blijven staan).')
                ->action(function (): void {
                    $site = Sites::getActive();

                    // Vervang alleen de wekelijkse regels; datum-uitzonderingen blijven.
                    ChatOpeningHour::where('site_id', $site)->whereNull('date')->delete();

                    foreach ([1, 2, 3, 4, 5] as $day) {
                        ChatOpeningHour::create([
                            'site_id' => $site,
                            'day_of_week' => $day,
                            'opens_at' => '08:00',
                            'closes_at' => '17:00',
                            'is_closed' => false,
                        ]);
                    }

                    ChatOpeningHour::create([
                        'site_id' => $site,
                        'day_of_week' => 6,
                        'opens_at' => '08:00',
                        'closes_at' => '12:00',
                        'is_closed' => false,
                    ]);

                    Notification::make()
                        ->title('Standaard openingstijden ingesteld')
                        ->body('Maandag t/m vrijdag 08:00-17:00, zaterdag 08:00-12:00.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
