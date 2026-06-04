<?php

namespace Dashed\DashedLivechat\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Dashed\DashedCore\Classes\Sites;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class ChatSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasSettingsPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Chat';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $formData["chat_enabled_{$site['id']}"] = (bool) Customsetting::get('chat_enabled', $site['id'], false);
            $formData["chat_primary_color_{$site['id']}"] = Customsetting::get('chat_primary_color', $site['id'], '#111827');
            $formData["chat_on_primary_color_{$site['id']}"] = Customsetting::get('chat_on_primary_color', $site['id'], '#ffffff');
            $formData["chat_radius_{$site['id']}"] = Customsetting::get('chat_radius', $site['id'], 16);
            $formData["chat_position_{$site['id']}"] = Customsetting::get('chat_position', $site['id'], 'right');
            $formData["chat_offset_{$site['id']}"] = Customsetting::get('chat_offset', $site['id'], 24);
            $formData["chat_title_{$site['id']}"] = Customsetting::get('chat_title', $site['id'], 'Chat met ons');
            $formData["chat_greeting_{$site['id']}"] = Customsetting::get('chat_greeting', $site['id'], 'Hoi! Waar kan ik je mee helpen?');
            $formData["chat_avatar_url_{$site['id']}"] = Customsetting::get('chat_avatar_url', $site['id'], null);
            $formData["chat_out_of_hours_behavior_{$site['id']}"] = Customsetting::get('chat_out_of_hours_behavior', $site['id'], 'ai_only');
            $formData["chat_search_driver_{$site['id']}"] = Customsetting::get('chat_search_driver', $site['id'], 'fulltext');
            $formData["chat_contact_phone_{$site['id']}"] = Customsetting::get('chat_contact_phone', $site['id']);
            $formData["chat_contact_email_{$site['id']}"] = Customsetting::get('chat_contact_email', $site['id']);
        }

        $this->form->fill($formData);
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema([
                    Toggle::make("chat_enabled_{$site['id']}")
                        ->label('Chat ingeschakeld'),
                    ColorPicker::make("chat_primary_color_{$site['id']}")
                        ->label('Primaire kleur'),
                    ColorPicker::make("chat_on_primary_color_{$site['id']}")
                        ->label('Tekst op primair'),
                    TextInput::make("chat_radius_{$site['id']}")
                        ->label('Hoekradius (px)')
                        ->numeric(),
                    Select::make("chat_position_{$site['id']}")
                        ->label('Positie')
                        ->options(['right' => 'Rechtsonder', 'left' => 'Linksonder']),
                    TextInput::make("chat_offset_{$site['id']}")
                        ->label('Marge (px)')
                        ->numeric(),
                    TextInput::make("chat_title_{$site['id']}")
                        ->label('Titel'),
                    Textarea::make("chat_greeting_{$site['id']}")
                        ->label('Begroeting'),
                    mediaHelper()->field("chat_avatar_url_{$site['id']}", 'Avatar', isImage: true),
                    Select::make("chat_out_of_hours_behavior_{$site['id']}")
                        ->label('Buiten openingstijden')
                        ->options([
                            'ai_only' => 'Alleen AI',
                            'contact_form' => 'Contactformulier',
                            'callback' => 'Terugbelverzoek',
                        ])
                        ->default('ai_only'),
                    Select::make("chat_search_driver_{$site['id']}")
                        ->label('Zoekstrategie')
                        ->options([
                            'fulltext' => 'Zoeken op tekst (LIKE)',
                            'embedding' => 'Semantisch (embeddings)',
                        ])
                        ->default('fulltext'),
                    TextInput::make("chat_contact_phone_{$site['id']}")
                        ->label('Snelcontact telefoonnummer')
                        ->tel()
                        ->helperText('Telefoonnummer voor snelcontact in de chat. Leeg = bedrijfsnummer gebruiken.'),
                    TextInput::make("chat_contact_email_{$site['id']}")
                        ->label('Snelcontact e-mailadres')
                        ->email()
                        ->helperText('E-mailadres voor snelcontact in de chat. Leeg = standaard site-e-mail gebruiken.'),
                ])
                ->columns(['default' => 1, 'lg' => 2]);
        }

        return $schema
            ->schema([Tabs::make('Sites')->tabs($tabs)])
            ->statePath('data');
    }

    public function submit(): void
    {
        foreach (Sites::getSites() as $site) {
            $state = $this->form->getState();
            Customsetting::set('chat_enabled', $state["chat_enabled_{$site['id']}"] ? '1' : '0', $site['id']);
            Customsetting::set('chat_primary_color', $state["chat_primary_color_{$site['id']}"] ?? '#111827', $site['id']);
            Customsetting::set('chat_on_primary_color', $state["chat_on_primary_color_{$site['id']}"] ?? '#ffffff', $site['id']);
            Customsetting::set('chat_radius', (string) ($state["chat_radius_{$site['id']}"] ?? 16), $site['id']);
            Customsetting::set('chat_position', $state["chat_position_{$site['id']}"] ?? 'right', $site['id']);
            Customsetting::set('chat_offset', (string) ($state["chat_offset_{$site['id']}"] ?? 24), $site['id']);
            Customsetting::set('chat_title', $state["chat_title_{$site['id']}"] ?? 'Chat met ons', $site['id']);
            Customsetting::set('chat_greeting', $state["chat_greeting_{$site['id']}"] ?? 'Hoi! Waar kan ik je mee helpen?', $site['id']);
            Customsetting::set('chat_avatar_url', $state["chat_avatar_url_{$site['id']}"] ?? null, $site['id']);
            Customsetting::set('chat_out_of_hours_behavior', $state["chat_out_of_hours_behavior_{$site['id']}"] ?? 'ai_only', $site['id']);
            Customsetting::set('chat_search_driver', $state["chat_search_driver_{$site['id']}"] ?? 'fulltext', $site['id']);
            Customsetting::set('chat_contact_phone', $state["chat_contact_phone_{$site['id']}"] ?? null, $site['id']);
            Customsetting::set('chat_contact_email', $state["chat_contact_email_{$site['id']}"] ?? null, $site['id']);
        }

        Notification::make()
            ->title('De chat-instellingen zijn opgeslagen')
            ->success()
            ->send();

        redirect(ChatSettingsPage::getUrl());
    }
}
