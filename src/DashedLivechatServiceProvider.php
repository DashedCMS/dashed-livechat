<?php

namespace Dashed\DashedLivechat;

use Livewire\Livewire;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dashed\DashedLivechat\Livewire\Frontend\ChatWidget;
use Dashed\DashedLivechat\Ai\Knowledge\CompositeSearchDriver;
use Dashed\DashedLivechat\Http\Controllers\StreamChatReplyController;
use Dashed\DashedLivechat\Ai\Knowledge\Contracts\KnowledgeSearchDriver;

class DashedLivechatServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $cms = cms();

        if (method_exists($cms, 'registerSettingsPage')) {
            $cms->registerSettingsPage(\Dashed\DashedLivechat\Filament\Pages\Settings\ChatSettingsPage::class, 'Chat');
            $cms->registerSettingsPage(\Dashed\DashedLivechat\Filament\Pages\Settings\WebPushKeyConfigPage::class, 'Web Push');
        }

        $package
            ->name('dashed-livechat')
            ->hasConfigFile()
            ->hasViews('dashed-livechat')
            ->hasCommands([
                \Dashed\DashedLivechat\Commands\IndexChatEmbeddings::class,
                \Dashed\DashedLivechat\Commands\SweepStaleConversationsCommand::class,
                \Dashed\DashedLivechat\Commands\NotifyVisitorCountCommand::class,
                \Dashed\DashedLivechat\Commands\GenerateWebPushKeysCommand::class,
            ])
            ->hasMigrations([
                'create_chat_agents_table',
                'create_chat_conversations_table',
                'create_chat_messages_table',
                'create_chat_knowledge_sources_table',
                'create_chat_knowledge_entries_table',
                'create_chat_triggers_table',
                'create_chat_events_table',
                'create_chat_opening_hours_table',
                'create_chat_embeddings_table',
                'add_feedback_to_chat_messages_table',
                'create_chat_learnings_table',
                'create_chat_visitor_sessions_table',
                'create_app_notifications_table',
                'create_chat_notes_table',
                'add_abilities_to_chat_agents_table',
                '2026_06_10_120000_add_model_links_to_chat_triggers_table',
                '2026_06_11_193000_widen_started_url_on_chat_conversations_table',
                'add_attachments_to_chat_messages_table',
                'create_chat_quick_replies_table',
                'add_ai_settings_to_chat_agents_table',
                'add_visitor_read_at_to_chat_conversations_table',
                'add_visitor_last_active_at_to_chat_conversations_table',
                'add_visitor_session_token_to_chat_conversations_table',
                'add_last_message_role_to_chat_conversations_table',
                'add_receive_outside_hours_to_chat_agents_table',
                'escalation_toggles_for_chat_agents_table',
                'is_sandbox_to_chat_conversations_table',
                'create_web_push_subscriptions_table',
                'create_web_push_preferences_table',
                '2026_07_30_120000_add_visitor_metadata_to_chat_conversations_table',
                '2026_07_31_090000_add_shortcut_and_owner_to_chat_quick_replies_table',
                '2026_07_31_100000_create_chat_tags_table',
                '2026_07_31_100100_create_chat_conversation_tag_table',
                '2026_07_31_110000_create_chat_unanswered_questions_table',
                '2026_07_31_120000_add_min_page_views_to_chat_triggers_table',
                '2026_07_31_130000_add_translation_to_chat_messages_table',
                '2026_07_31_130100_add_auto_translate_to_chat_conversations_table',
            ])
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(KnowledgeSearchDriver::class, CompositeSearchDriver::class);
    }

    public function bootingPackage(): void
    {
        Livewire::component('chat.widget', ChatWidget::class);
        $this->app->booted(function () {
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
                ->appendMiddlewareToGroup('web', \Dashed\DashedLivechat\Http\Middleware\InjectChatWidget::class);

            // Always-on presence-heartbeat op elke frontend-pagina.
            $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
                ->appendMiddlewareToGroup('web', \Dashed\DashedLivechat\Http\Middleware\InjectVisitorPresence::class);

            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            // Markeer gesprekken inactief (15 min) en afgerond (1 uur) zonder reactie.
            $schedule->command('dashed-livechat:sweep-stale-conversations')->everyFiveMinutes();
            // Zet (max 1 per 5 min) een app-notificatie klaar met het aantal live bezoekers.
            $schedule->command('dashed-livechat:notify-visitor-count')->everyFiveMinutes();
            // Houd de semantische kennis-index (embeddings) dagelijks vers; de
            // command slaat zichzelf over op sites zonder embedding-zoekstrategie.
            $schedule->command('chat:index-embeddings')->dailyAt('04:00');
        });

        Route::middleware(['web', 'throttle:30,1'])
            ->get('dashed-livechat/stream/{token}', StreamChatReplyController::class)
            ->name('dashed-livechat.stream');

        Route::middleware(['web', 'throttle:120,1'])
            ->get('dashed-livechat/presence', \Dashed\DashedLivechat\Http\Controllers\RecordVisitorPresenceController::class)
            ->name('dashed-livechat.presence');

        Route::middleware(['web', 'auth'])
            ->post('dashed-livechat/web-push/subscribe', [\Dashed\DashedLivechat\Http\Controllers\WebPushSubscriptionController::class, 'store'])
            ->name('dashed-livechat.web-push.subscribe');

        Route::middleware(['web', 'auth'])
            ->delete('dashed-livechat/web-push/subscribe', [\Dashed\DashedLivechat\Http\Controllers\WebPushSubscriptionController::class, 'destroy'])
            ->name('dashed-livechat.web-push.unsubscribe');

        Route::middleware(['web'])
            ->get('dashed-livechat-sw.js', \Dashed\DashedLivechat\Http\Controllers\WebPushServiceWorkerController::class)
            ->name('dashed-livechat.web-push.sw');

        $cms = cms();

        if (method_exists($cms, 'registerNavigationGroup')) {
            $cms->registerNavigationGroup('Chat', 26);
        }

        $cms->builder('plugins', [
            new \Dashed\DashedLivechat\DashedLivechatPlugin(),
        ]);

        if (class_exists(\Dashed\DashedMobileApi\MobileApiRegistry::class)) {
            /** @var \Dashed\DashedMobileApi\MobileApiRegistry $mobileApi */
            $mobileApi = $this->app->make(\Dashed\DashedMobileApi\MobileApiRegistry::class);

            $version = \Composer\InstalledVersions::isInstalled('dashed/dashed-livechat')
                ? \Composer\InstalledVersions::getPrettyVersion('dashed/dashed-livechat')
                : null;
            $mobileApi->registerCapability('livechat', ['version' => $version]);

            $mobileApi->registerAbilities(['chat.read', 'chat.reply', 'chat.takeover', 'chat.manage']);

            // Middleware die livechat-toegang per site + per medewerker afdwingt.
            app('router')->aliasMiddleware('chat.ability', \Dashed\DashedLivechat\Http\Middleware\EnsureChatAgentAbility::class);

            $mobileApi->registerNotificationTypes([
                ['key' => 'chat.handoff', 'label' => 'Nieuwe chat', 'description' => 'Een bezoeker vraagt om een medewerker.', 'group' => 'Livechat', 'sound' => 'chat', 'ability' => 'chat.read', 'default' => true],
                ['key' => 'chat.message', 'label' => 'Nieuw chatbericht', 'description' => 'Een bezoeker stuurt een nieuw bericht in de chat (zowel AI- als mens-gesprekken).', 'group' => 'Livechat', 'sound' => 'chat', 'ability' => 'chat.read', 'default' => true],
                ['key' => 'visitors.live', 'label' => 'Live bezoekers', 'description' => 'Krijg met tussenpozen een melding hoeveel bezoekers er nu op de website zijn.', 'group' => 'Livechat', 'sound' => 'default', 'ability' => 'chat.read', 'default' => false],
            ]);

            // De app krijgt via /capabilities de effectieve livechat-rechten van
            // de ingelogde user voor de actieve site. Alleen geregistreerde
            // medewerkers (of superadmin) hebben toegang.
            $mobileApi->registerCapabilityContextContributor(function ($user, string $siteId): array {
                return [
                    'chat' => [
                        'is_agent' => \Dashed\DashedLivechat\Support\ChatAccess::isAgent($user, $siteId),
                        'abilities' => \Dashed\DashedLivechat\Support\ChatAccess::abilitiesForUser($user, $siteId),
                    ],
                ];
            });

            $mobileApi->registerDashboardContributor(function (string $siteId, $period): array {
                $model = \Dashed\DashedLivechat\Models\ChatConversation::class;

                // Livechat-cijfers tonen bewust de HUIDIGE live-stand en zijn
                // niet afhankelijk van de geselecteerde dashboard-periode.
                $active = fn () => $model::query()
                    ->where('site_id', $siteId)
                    ->where('status', 'active');

                // Escalaties = nu openstaande gesprekken die op een medewerker wachten.
                $escalations = (clone $active())->where('mode', 'waiting_human')->count();

                return [
                    'conversations_waiting_human' => (clone $active())->where('mode', 'waiting_human')->count(),
                    'open_conversations' => (clone $active())->count(),
                    'chat_modes' => [
                        'ai' => (clone $active())->where('mode', 'ai')->count(),
                        'waiting_human' => (clone $active())->where('mode', 'waiting_human')->count(),
                        'human' => (clone $active())->where('mode', 'human')->count(),
                    ],
                    'chat_escalations' => $escalations,
                ];
            });

            // Globaal zoeken: gesprekken. Zelfde zoekvelden als ConversationController@index
            // (?search over visitor_name/visitor_email), gescope't op de actieve site.
            if (method_exists($mobileApi, 'registerSearchProvider')) {
                $mobileApi->registerSearchProvider(function (string $siteId, string $query): array {
                    $conversations = \Dashed\DashedLivechat\Models\ChatConversation::query()
                        ->where('site_id', $siteId)
                        ->where(function ($q) use ($query): void {
                            $q->where('visitor_name', 'like', '%' . $query . '%')
                                ->orWhere('visitor_email', 'like', '%' . $query . '%');
                        })
                        ->orderByDesc('last_message_at')
                        ->limit(5)
                        ->get(['id', 'visitor_name', 'visitor_email']);

                    return $conversations->map(function ($conversation): array {
                        $name = trim((string) ($conversation->visitor_name ?? '')) ?: 'Bezoeker';

                        return [
                            'type' => 'conversation',
                            'id' => $conversation->id,
                            'title' => $name,
                            'subtitle' => $conversation->visitor_email ? (string) $conversation->visitor_email : null,
                            'route' => "/conversation/{$conversation->id}",
                        ];
                    })->all();
                });
            }

            $this->loadRoutesFrom(__DIR__ . '/../routes/mobile-api.php');
        }
    }
}
