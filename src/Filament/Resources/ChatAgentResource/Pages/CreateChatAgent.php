<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatAgentResource\Pages;

use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Sites;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatAgentResource;
use Dashed\DashedLivechat\Services\AgentConfigSuggestionService;

class CreateChatAgent extends CreateRecord
{
    protected static string $resource = ChatAgentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Sites::getActive();

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('vulMetAi')
                ->label('Vul met AI')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->action(function (): void {
                    $siteId = $this->data['site_id'] ?? Sites::getActive() ?? 'main';

                    try {
                        $suggestion = app(AgentConfigSuggestionService::class)->suggest((string) $siteId);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('AI-suggestie mislukt')
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
                        ->title('AI-suggestie geladen')
                        ->body('De gedragsvelden zijn ingevuld. Controleer en pas aan naar wens.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
