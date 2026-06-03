<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages;

use Filament\Resources\Pages\Page;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\HandoffService;
use Dashed\DashedLivechat\Services\ConversationManager;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource;

class ViewChatConversation extends Page
{
    protected static string $resource = ChatConversationResource::class;

    protected string $view = 'dashed-livechat::filament.conversation-timeline';

    public string $mode = 'ai';

    public string $reply = '';

    /** Resolved ChatConversation, stored separately to avoid type conflict with the Filament trait's $record property. */
    public ?ChatConversation $conversation = null;

    public function mount(int|string $record): void
    {
        $this->conversation = ChatConversation::with('messages.agent')->findOrFail($record);
        $this->mode = $this->conversation->mode;
    }

    public function takeOver(): void
    {
        $this->requireAuth();
        $handoff = app(HandoffService::class);
        $agent = $handoff->humanAgentForUser(auth()->user(), $this->conversation->site_id);
        $handoff->takeOver($this->conversation, $agent);
        $this->refreshRecord();
    }

    public function releaseToAi(): void
    {
        $this->requireAuth();
        $handoff = app(HandoffService::class);
        $handoff->release($this->conversation);
        $this->refreshRecord();
    }

    public function sendReply(): void
    {
        $this->requireAuth();
        $text = trim($this->reply);
        if ($text === '') {
            return;
        }

        $handoff = app(HandoffService::class);
        $manager = app(ConversationManager::class);
        $agent = $handoff->humanAgentForUser(auth()->user(), $this->conversation->site_id);
        $manager->addHumanMessage($this->conversation, $agent, $text);
        $this->reply = '';
        $this->refreshRecord();
    }

    public function pollMessages(): void
    {
        $this->refreshRecord();
    }

    protected function refreshRecord(): void
    {
        $this->conversation = ChatConversation::with('messages.agent')->findOrFail($this->conversation->id);
        $this->mode = $this->conversation->mode;
    }

    private function requireAuth(): void
    {
        abort_unless(auth()->check(), 403);
    }
}
