<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages;

use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatLearning;
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

    public function markFeedback(int $messageId, string $value): void
    {
        $this->requireAuth();
        $message = ChatMessage::findOrFail($messageId);
        $message->feedback = $value;
        $message->save();
        $this->refreshRecord();
        Notification::make()->title('Feedback opgeslagen')->success()->send();
    }

    public function learnFromMessage(int $messageId): void
    {
        $this->requireAuth();
        $message = ChatMessage::findOrFail($messageId);

        $question = '';
        if ($message->role === 'ai') {
            $preceding = $this->conversation->messages
                ->where('role', 'visitor')
                ->where('id', '<', $messageId)
                ->sortByDesc('id')
                ->first();
            $question = $preceding?->content ?? '';
        }

        if ($message->role === 'human') {
            $source = 'human';
        } elseif ($message->role === 'ai' && $message->feedback === 'bad') {
            $source = 'feedback';
        } else {
            $source = 'ai';
        }

        ChatLearning::create([
            'site_id' => $this->conversation->site_id,
            'question' => $question,
            'answer' => $message->content,
            'source' => $source,
            'source_message_id' => $message->id,
            'is_active' => true,
        ]);

        Notification::make()
            ->title('Toegevoegd aan wat de bot leert. Pas het antwoord eventueel aan bij Chat -> Geleerd.')
            ->success()
            ->send();
    }

    public function pollMessages(): void
    {
        $this->refreshRecord();
    }

    /**
     * E-mailadressen die in het gesprek voorkomen, inclusief het bekende
     * bezoekers-e-mailadres. Basis voor het koppelen van bestellingen/klant.
     */
    public function getDetectedEmailsProperty(): array
    {
        $emails = collect();

        if ($this->conversation?->visitor_email) {
            $emails->push($this->conversation->visitor_email);
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $this->conversationText(), $matches);

        return $emails->merge($matches[0] ?? [])
            ->map(fn ($email) => mb_strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Mogelijke ordernummers/referenties uit het gesprek: alfanumerieke
     * tokens met minstens één cijfer (zo vangen we bijv. ordernummers en
     * factuur-id's). De daadwerkelijke filtering gebeurt op de database.
     */
    public function getDetectedOrderRefsProperty(): array
    {
        preg_match_all('/#?\b([A-Z0-9][A-Z0-9\-]{3,19})\b/i', $this->conversationText(), $matches);

        return collect($matches[1] ?? [])
            ->map(fn ($token) => trim($token, " \t\n\r#"))
            ->filter(fn ($token) => preg_match('/\d/', $token) && ! filter_var($token, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    public function getRelatedOrdersProperty(): Collection
    {
        if (! class_exists(Order::class)) {
            return collect();
        }

        $emails = $this->detectedEmails;
        $refs = $this->detectedOrderRefs;

        if (empty($emails) && empty($refs)) {
            return collect();
        }

        return Order::query()
            ->where('site_id', $this->conversation->site_id)
            ->where('status', '!=', Order::STATUS_CONCEPT)
            ->where(function ($query) use ($emails, $refs) {
                if ($emails) {
                    $query->orWhereIn(DB::raw('LOWER(email)'), $emails);
                }

                if ($refs) {
                    $query->orWhereIn('invoice_id', $refs)
                        ->orWhereIn('hash', $refs);
                }
            })
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function getRelatedCustomersProperty(): Collection
    {
        $userClass = \Dashed\DashedCore\Models\User::class;

        if (! class_exists($userClass)) {
            return collect();
        }

        $emails = $this->detectedEmails;

        if (empty($emails)) {
            return collect();
        }

        return $userClass::query()
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->limit(5)
            ->get();
    }

    public function orderUrl($order): ?string
    {
        $resource = \Dashed\DashedEcommerceCore\Filament\Resources\OrderResource::class;

        if (! class_exists($resource)) {
            return null;
        }

        try {
            return $resource::getUrl('view', ['record' => $order->getKey()]);
        } catch (\Throwable) {
            return null;
        }
    }

    public function customerUrl($user): ?string
    {
        $resource = \Dashed\DashedCore\Filament\Resources\UserResource::class;

        if (! class_exists($resource)) {
            return null;
        }

        try {
            return $resource::getUrl('edit', ['record' => $user->getKey()]);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function conversationText(): string
    {
        return (string) ($this->conversation?->messages?->pluck('content')->join("\n") ?? '');
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
