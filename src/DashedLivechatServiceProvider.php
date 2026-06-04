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
        });

        Route::middleware(['web', 'throttle:30,1'])
            ->get('dashed-livechat/stream/{token}', StreamChatReplyController::class)
            ->name('dashed-livechat.stream');

        $cms = cms();

        if (method_exists($cms, 'registerNavigationGroup')) {
            $cms->registerNavigationGroup('Chat', 26);
        }

        $cms->builder('plugins', [
            new \Dashed\DashedLivechat\DashedLivechatPlugin(),
        ]);
    }
}
