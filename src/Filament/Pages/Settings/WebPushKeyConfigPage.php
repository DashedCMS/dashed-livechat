<?php

namespace Dashed\DashedLivechat\Filament\Pages\Settings;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Minishlink\WebPush\VAPID;
use Illuminate\Support\HtmlString;
use Dashed\DashedCore\Classes\Sites;
use Filament\Schemas\Components\Tabs;
use Illuminate\Support\Facades\Crypt;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Contracts\HasSchemas;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedCore\Traits\HasSettingsPermission;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Dashed\DashedLivechat\Models\WebPushSubscription;

class WebPushKeyConfigPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasSettingsPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Web Push instellingen';

    protected string $view = 'dashed-core::settings.pages.default-settings';

    public array $data = [];

    public function mount(): void
    {
        $formData = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $formData["web_push_public_key_{$id}"] = Customsetting::get('web_push_public_key', $id);
            // Private key nooit tonen; leeg laten betekent "ongewijzigd".
            $formData["web_push_private_key_{$id}"] = '';
            $formData["web_push_subject_{$id}"] = Customsetting::get('web_push_subject', $id, 'mailto:info@dashed.nl');
        }

        $this->form->fill($formData);
    }

    public function generateKeysAction(string $siteId): Action
    {
        return Action::make("generate_{$siteId}")
            ->label(__('Genereer sleutelpaar'))
            ->icon('heroicon-o-key')
            ->action(fn () => $this->generateKeys($siteId));
    }

    public function form(Schema $schema): Schema
    {
        $tabs = [];
        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $tabs[] = Tab::make($id)
                ->label(ucfirst($site['name']))
                ->schema([
                    Placeholder::make("web_push_help_{$id}")
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(new HtmlString(
                            '<div style="font-size: 0.875rem; line-height: 1.55;">'
                            . '<p><strong>Wat is dit?</strong> VAPID-sleutels identificeren jouw server bij de push-diensten van de browsers, zodat bureaubladmeldingen afgeleverd mogen worden. Zonder sleutels blijft Web Push voor deze site uit.</p>'
                            . '<p>Klik op <strong>Genereer sleutelpaar</strong> voor een nieuw paar (eenmalig, gratis, lokaal gegenereerd), vul een <strong>subject</strong> in (een mailto: of https: URL waarop je bereikbaar bent) en sla op.</p>'
                            . '<p><strong>Let op:</strong> Web Push werkt alleen over HTTPS (localhost uitgezonderd). Nieuwe sleutels maken bestaande apparaat-aanmeldingen van deze site ongeldig; medewerkers moeten dan opnieuw inschakelen via Chat &gt; Bureaubladmeldingen.</p>'
                            . '</div>'
                        )),
                    Actions::make([
                        $this->generateKeysAction($id),
                    ]),
                    TextInput::make("web_push_public_key_{$id}")
                        ->label(__('Publieke sleutel'))
                        ->helperText(__('Wordt aan de browser meegegeven bij het abonneren.')),
                    TextInput::make("web_push_private_key_{$id}")
                        ->label(__('Private sleutel'))
                        ->password()
                        ->revealable()
                        ->helperText(__('Leeg laten houdt de opgeslagen sleutel ongewijzigd.')),
                    TextInput::make("web_push_subject_{$id}")
                        ->label(__('Subject'))
                        ->helperText(__('mailto: of https: URL die jouw dienst identificeert.')),
                ]);
        }

        return $schema
            ->schema([Tabs::make('sites')->tabs($tabs)])
            ->statePath('data');
    }

    public function generateKeys(string $siteId): void
    {
        $keys = VAPID::createVapidKeys();
        $this->data["web_push_public_key_{$siteId}"] = $keys['publicKey'];
        $this->data["web_push_private_key_{$siteId}"] = $keys['privateKey'];

        Notification::make()
            ->title(__('Sleutelpaar gegenereerd'))
            ->body(__('Controleer en sla op om het te bewaren.'))
            ->success()
            ->send();
    }

    public function submit(): void
    {
        $state = $this->form->getState();

        foreach (Sites::getSites() as $site) {
            $id = $site['id'];
            $newPublic = $state["web_push_public_key_{$id}"] ?? null;
            $oldPublic = Customsetting::get('web_push_public_key', $id);

            Customsetting::set('web_push_public_key', $newPublic ?: null, $id);
            Customsetting::set('web_push_subject', $state["web_push_subject_{$id}"] ?? null, $id);

            $newPrivate = $state["web_push_private_key_{$id}"] ?? '';
            if ($newPrivate !== '') {
                Customsetting::set('web_push_private_key', Crypt::encryptString($newPrivate), $id);
            }

            // Publieke sleutel gewijzigd (of leeggemaakt): bestaande aanmeldingen
            // horen bij de oude sleutel en werken niet meer, dus opruimen.
            if (($oldPublic ?: '') !== ($newPublic ?: '')) {
                WebPushSubscription::where('site_id', $id)->delete();
            }
        }

        Notification::make()
            ->title(__('Web Push-instellingen opgeslagen'))
            ->success()
            ->send();
    }
}
