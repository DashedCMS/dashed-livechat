<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatAgentResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Dashed\DashedCore\Classes\Sites;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatAgentResource;
use Dashed\DashedLivechat\Services\AgentConfigSuggestionService;

class EditChatAgent extends EditRecord
{
    protected static string $resource = ChatAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vulMetAi')
                ->label(__('Vul met AI'))
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->action(function (): void {
                    $siteId = $this->data['site_id'] ?? $this->record->site_id ?? Sites::getActive() ?? 'main';

                    try {
                        $suggestion = app(AgentConfigSuggestionService::class)->suggest((string) $siteId);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title(__('AI-suggestie mislukt'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $behaviorKeys = ['persona', 'tone', 'languages', 'allowed_topics', 'disallowed_topics', 'escalation_rules', 'greeting'];

                    foreach ($behaviorKeys as $key) {
                        if (isset($suggestion[$key])) {
                            $this->data[$key] = $suggestion[$key];
                        }
                    }

                    $this->form->fill($this->data);

                    Notification::make()
                        ->title(__('AI-suggestie geladen'))
                        ->body(__('De gedragsvelden zijn ingevuld. Controleer en pas aan naar wens.'))
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
        ];
    }
}
