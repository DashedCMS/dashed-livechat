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
        }

        $package
            ->name('dashed-livechat')
            ->hasConfigFile()
            ->hasViews('dashed-livechat')
            ->hasCommands([
                \Dashed\DashedLivechat\Commands\IndexChatEmbeddings::class,
                \Dashed\DashedLivechat\Commands\SweepStaleConversationsCommand::class,
                \Dashed\DashedLivechat\Commands\NotifyVisitorCountCommand::class,
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
        });

        Route::middleware(['web', 'throttle:30,1'])
            ->get('dashed-livechat/stream/{token}', StreamChatReplyController::class)
            ->name('dashed-livechat.stream');

        Route::middleware(['web', 'throttle:120,1'])
            ->get('dashed-livechat/presence', \Dashed\DashedLivechat\Http\Controllers\RecordVisitorPresenceController::class)
            ->name('dashed-livechat.presence');

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

            $mobileApi->registerAbilities(['chat.read', 'chat.reply', 'chat.takeover']);
            $mobileApi->registerRoleAbilities([
                'eigenaar' => ['chat.read', 'chat.reply', 'chat.takeover'],
                'admin' => ['chat.read', 'chat.reply', 'chat.takeover'],
                'support-agent' => ['chat.read', 'chat.reply', 'chat.takeover'],
                'read-only' => ['chat.read'],
            ]);

            $mobileApi->registerDashboardContributor(function (string $siteId, $period): array {
                $model = \Dashed\DashedLivechat\Models\ChatConversation::class;

                return [
                    'conversations_waiting_human' => $model::query()
                        ->where('site_id', $siteId)
                        ->where('mode', 'waiting_human')
                        ->whereBetween('created_at', [$period->start, $period->end])
                        ->count(),
                    'open_conversations' => $model::query()
                        ->where('site_id', $siteId)
                        ->where('status', 'active')
                        ->whereBetween('created_at', [$period->start, $period->end])
                        ->count(),
                ];
            });

            $this->loadRoutesFrom(__DIR__ . '/../routes/mobile-api.php');
        }
    }
}
