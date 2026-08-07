<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages;

use Filament\Actions\Action;
use Livewire\WithFileUploads;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Ai\LivechatAi;
use Filament\Notifications\Notification;
use Dashed\DashedLivechat\Models\ChatTag;
use Dashed\DashedLivechat\Models\ChatNote;
use Dashed\DashedLivechat\Models\ChatUnansweredQuestion;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Support\ChatAccess;
use Dashed\DashedLivechat\Models\ChatLearning;
use Dashed\DashedLivechat\Models\ChatQuickReply;
use Dashed\DashedLivechat\Support\SnippetRenderer;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\HandoffService;
use Dashed\DashedLivechat\Services\ConversationManager;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource;

class ViewChatConversation extends Page
{
    use WithFileUploads;

    protected static string $resource = ChatConversationResource::class;

    protected string $view = 'dashed-livechat::filament.conversation-timeline';

    public string $mode = 'ai';

    /** Gesprekstatus, reactief via de Status-keuzelijst in de toolbar. */
    public string $status = 'active';

    public string $reply = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $replyAttachments = [];

    public string $noteBody = '';

    /** Id van het laatste bericht; via @entangle reactief in Alpine om bij een nieuw bericht naar onder te scrollen. */
    public int $lastMessageId = 0;

    /** Resolved ChatConversation, stored separately to avoid type conflict with the Filament trait's $record property. */
    public ?ChatConversation $conversation = null;

    public function mount(int|string $record): void
    {
        $this->conversation = ChatConversation::with('messages.agent', 'tags', 'visitorSession')->findOrFail($record);
        $this->mode = $this->conversation->mode;
        $this->status = $this->conversation->status;
        $this->lastMessageId = (int) ($this->conversation->messages->max('id') ?? 0);
    }

    /** Status wisselen via de keuzelijst in de toolbar. */
    public function updatedStatus(string $value): void
    {
        $this->requireAuth();
        if (! in_array($value, ['active', 'closed'], true)) {
            return;
        }
        $this->conversation->forceFill(['status' => $value])->save();
        Notification::make()
            ->success()
            ->title($value === 'closed' ? __('Gesprek afgerond') : __('Gesprek heractiveerd'))
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            // Afronden/heropenen gebeurt nu via de Status-keuzelijst in de toolbar.
            Action::make('delete')
                ->label(__('Verwijderen'))
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->conversation->delete();
                    $this->redirect(ChatConversationResource::getUrl('index'));
                }),
        ];
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
        $hasFiles = ! empty($this->replyAttachments);
        if ($text === '' && ! $hasFiles) {
            return;
        }
        if ($hasFiles) {
            $this->validate([
                'replyAttachments' => ['array', 'max:5'],
                'replyAttachments.*' => ['file', 'max:10240', \Dashed\DashedLivechat\Support\AttachmentRules::clientImageOrPdf()],
            ]);
        }

        $handoff = app(HandoffService::class);
        $manager = app(ConversationManager::class);
        $agent = $handoff->humanAgentForUser(auth()->user(), $this->conversation->site_id);

        try {
            $manager->addHumanMessage($this->conversation, $agent, $text, $this->replyAttachments);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title(__('Versturen mislukt'))->body($e->getMessage())->danger()->send();

            return;
        }
        $this->reply = '';
        $this->replyAttachments = [];
        $this->refreshRecord();
    }

    /**
     * Antwoord-snippets die de ingelogde medewerker mag gebruiken op deze
     * site: gedeelde (team) snippets + haar/zijn eigen persoonlijke.
     */
    public function getQuickRepliesProperty(): Collection
    {
        return ChatQuickReply::query()
            ->visibleTo(auth()->id(), $this->conversation->site_id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * Voegt de (resolved) inhoud van een snippet in als concept-antwoord.
     * Vervangt het huidige conceptantwoord — de medewerker kan het daarna
     * nog aanpassen vóór verzenden.
     */
    public function insertQuickReply(int $quickReplyId): void
    {
        $this->requireAuth();

        $quickReply = ChatQuickReply::query()
            ->visibleTo(auth()->id(), $this->conversation->site_id)
            ->find($quickReplyId);

        if (! $quickReply) {
            return;
        }

        $this->reply = SnippetRenderer::render($quickReply->content, $this->conversation, auth()->user(), $this->siteName());
    }

    /**
     * Alpine detecteert client-side wanneer de medewerker `/<shortcut>`
     * gevolgd door een spatie typt in het antwoordveld en roept dit dan aan
     * met de volledige huidige inhoud. Bij een match wordt het conceptantwoord
     * vervangen door de (resolved) snippet-inhoud; anders gebeurt er niets.
     */
    public function expandShortcut(string $typed): void
    {
        $this->requireAuth();

        if (! preg_match('/^\/([a-z0-9_-]+)\s$/i', $typed, $matches)) {
            return;
        }

        $quickReply = ChatQuickReply::query()
            ->visibleTo(auth()->id(), $this->conversation->site_id)
            ->whereRaw('LOWER(shortcut) = ?', [mb_strtolower($matches[1])])
            ->first();

        if (! $quickReply) {
            return;
        }

        $this->reply = SnippetRenderer::render($quickReply->content, $this->conversation, auth()->user(), $this->siteName());
    }

    private function siteName(): string
    {
        return (string) (Sites::get($this->conversation->site_id)['name'] ?? $this->conversation->site_id);
    }

    public function markFeedback(int $messageId, string $value): void
    {
        $this->requireAuth();
        $message = ChatMessage::findOrFail($messageId);
        $message->feedback = $value;
        $message->save();

        // Negatieve feedback op een AI-antwoord = signaal dat de kennisbank
        // tekortschiet → leg de bijbehorende bezoekersvraag vast voor review.
        if (in_array($value, ['down', 'negative'], true) && $message->role === 'ai') {
            $preceding = $this->conversation->messages
                ->where('role', 'visitor')
                ->where('id', '<', $messageId)
                ->sortByDesc('id')
                ->first();
            ChatUnansweredQuestion::capture($this->conversation, 'negative_feedback', $preceding?->content);
        }

        $this->refreshRecord();
        Notification::make()->title(__('Feedback opgeslagen'))->success()->send();
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
            ->title(__('Toegevoegd aan wat de bot leert. Pas het antwoord eventueel aan bij Chat -> Geleerd.'))
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

    public function getNotesProperty()
    {
        return ChatNote::where('chat_conversation_id', $this->conversation->id)
            ->orderByDesc('id')
            ->get();
    }

    public function addNote(): void
    {
        $this->requireAuth();

        $body = trim($this->noteBody);
        if ($body === '') {
            return;
        }

        ChatNote::create([
            'chat_conversation_id' => $this->conversation->id,
            'user_id' => auth()->id(),
            'author_name' => auth()->user()?->name,
            'body' => $body,
        ]);

        $this->noteBody = '';
        Notification::make()->title(__('Notitie opgeslagen'))->success()->send();
    }

    /** Alle tags van de actieve site (voor de tag-badges op het gesprek). */
    public function getAvailableTagsProperty(): Collection
    {
        return ChatTag::query()
            ->where('site_id', (string) Sites::getActive())
            ->orderBy('sort')
            ->orderBy('name')
            ->get();
    }

    /** Koppelt/ontkoppelt een tag (alleen tags van de eigen site). */
    public function toggleTag(int $tagId): void
    {
        $this->requireAuth();

        $tag = ChatTag::query()
            ->where('site_id', (string) Sites::getActive())
            ->find($tagId);

        if (! $tag) {
            return;
        }

        $this->conversation->tags()->toggle([$tag->id]);
        $this->conversation->load('tags');
    }

    /**
     * Laat de AI een concept-antwoord voorstellen op basis van het gesprek.
     * Vult het antwoordveld; verstuurt niets (de medewerker kiest zelf).
     */
    public function suggestReply(): void
    {
        $this->requireAuth();

        try {
            $draft = \Dashed\DashedLivechat\Support\ReplySuggester::suggest($this->conversation);
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title(__('Kon geen suggestie ophalen'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        if ($draft === '') {
            Notification::make()->title(__('Geen suggestie ontvangen'))->warning()->send();

            return;
        }

        $this->reply = $draft;
        Notification::make()->title(__('Concept-antwoord ingevuld'))->success()->send();
    }

    protected function refreshRecord(): void
    {
        $this->conversation = ChatConversation::with('messages.agent')->findOrFail($this->conversation->id);
        $this->mode = $this->conversation->mode;
        $this->status = $this->conversation->status;
        $this->lastMessageId = (int) ($this->conversation->messages->max('id') ?? 0);
    }

    private function requireAuth(): void
    {
        abort_unless(auth()->check(), 403);

        // Alleen chat-agents (of superadmins) van de actieve site mogen chat-acties uitvoeren,
        // niet zomaar elke ingelogde beheerder.
        abort_unless(
            ChatAccess::isAgent(auth()->user(), (string) Sites::getActive()),
            403
        );
    }
}
