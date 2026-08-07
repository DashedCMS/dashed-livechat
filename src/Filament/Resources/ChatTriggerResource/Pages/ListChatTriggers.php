<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatTriggerResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Models\ChatTrigger;
use Dashed\DashedLivechat\Services\TriggerSuggestionService;
use Dashed\DashedLivechat\Filament\Resources\ChatTriggerResource;

class ListChatTriggers extends ListRecords
{
    protected static string $resource = ChatTriggerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('suggestTriggers')
                ->label(__('AI-voorstellen'))
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->form(function (): array {
                    $siteId = Sites::getActive() ?: 'main';
                    $suggestions = [];

                    try {
                        $suggestions = app(TriggerSuggestionService::class)->suggest($siteId);
                    } catch (\Throwable) {
                        // Als de AI niet beschikbaar is, beginnen we met een leeg formulier
                    }

                    if (empty($suggestions)) {
                        $suggestions = [[
                            'name' => '',
                            'placement' => 'all_pages',
                            'url_rules' => [],
                            'trigger_type' => 'none',
                            'trigger_value' => null,
                            'proactive_message' => '',
                            'toevoegen' => false,
                        ]];
                    } else {
                        $suggestions = array_map(fn ($s) => array_merge($s, ['toevoegen' => true]), $suggestions);
                    }

                    return [
                        Repeater::make('voorstellen')
                            ->label(__('Trigger-voorstellen'))
                            ->helperText(__('Vink "Toevoegen" aan om een voorstel op te nemen. Pas de velden naar wens aan.'))
                            ->default($suggestions)
                            ->schema([
                                Toggle::make('toevoegen')
                                    ->label(__('Toevoegen'))
                                    ->default(true)
                                    ->columnSpanFull(),
                                TextInput::make('name')
                                    ->label(__('Naam'))
                                    ->required(),
                                Select::make('placement')
                                    ->label(__('Plaatsing'))
                                    ->options([
                                        'all_pages' => __('Alle pagina\'s'),
                                        'include_urls' => __('Specifieke URL\'s'),
                                        'url_pattern' => __('URL-patroon'),
                                    ])
                                    ->default('all_pages'),
                                Select::make('trigger_type')
                                    ->label(__('Trigger-type'))
                                    ->options([
                                        'none' => __('Geen'),
                                        'immediate' => __('Direct'),
                                        'time_on_page' => __('Tijd op pagina'),
                                        'scroll_depth' => __('Scroll-diepte'),
                                        'exit_intent' => __('Vertrekintentie'),
                                    ])
                                    ->default('none'),
                                TextInput::make('trigger_value')
                                    ->label(__('Triggerwaarde'))
                                    ->numeric()
                                    ->nullable(),
                                Textarea::make('proactive_message')
                                    ->label(__('Proactief bericht'))
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?: null),
                    ];
                })
                ->action(function (array $data): void {
                    $siteId = Sites::getActive() ?: 'main';
                    $voorstellen = $data['voorstellen'] ?? [];
                    $count = 0;

                    foreach ($voorstellen as $voorstel) {
                        if (! ($voorstel['toevoegen'] ?? false)) {
                            continue;
                        }

                        ChatTrigger::create([
                            'site_id' => $siteId,
                            'name' => $voorstel['name'] ?? 'AI-voorstel',
                            'is_active' => true,
                            'sort_order' => 0,
                            'placement' => $voorstel['placement'] ?? 'all_pages',
                            'url_rules' => $voorstel['url_rules'] ?? [],
                            'exclude_urls' => [],
                            'trigger_type' => $voorstel['trigger_type'] ?? 'none',
                            'trigger_value' => $voorstel['trigger_value'] ?? null,
                            'proactive_message' => $voorstel['proactive_message'] ?? '',
                        ]);

                        $count++;
                    }

                    Notification::make()
                        ->title($count > 0
                            ? __(':aantal trigger(s) toegevoegd', ['aantal' => $count])
                            : __('Geen triggers toegevoegd'))
                        ->body($count > 0
                            ? __('De geselecteerde triggers zijn opgeslagen.')
                            : __('Selecteer minimaal een voorstel om toe te voegen.'))
                        ->success()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
