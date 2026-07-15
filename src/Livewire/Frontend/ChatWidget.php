<?php

// src/Livewire/Frontend/ChatWidget.php

namespace Dashed\DashedLivechat\Livewire\Frontend;

use Livewire\Component;
use Livewire\WithFileUploads;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatAgent;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedLivechat\Guardrails\InputGuard;
use Dashed\DashedLivechat\Jobs\GenerateAiReplyJob;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\ChatAvailability;
use Dashed\DashedLivechat\Services\ConversationManager;

class ChatWidget extends Component
{
    use WithFileUploads;

    public string $siteId = '';
    public ?string $publicToken = null;

    /** Site-breed bezoekers-token (uit localStorage 'dashed_visitor_token') dat de
     *  presence-beacon gebruikt; opgeslagen op het gesprek zodat we de aanwezigheid
     *  scherp kunnen bepalen (voorgrond vs achtergrond-op-de-site vs weg). */
    public ?string $visitorSessionToken = null;
    public string $draft = '';
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $newAttachments = [];
    public bool $open = false;
    public bool $awaitingReply = false;
    public int $lastMessageId = 0;
    public string $lastMessagePreview = '';
    public ?int $proactiveTriggerId = null;
    public ?array $trigger = null;
    public ?string $streamUrl = null;

    // Feature C: conversational contact capture
    public bool $contactDismissed = false;
    public ?string $contactStep = null; // null | 'email' | 'name'

    public function mount(?string $siteId = null, ?array $trigger = null): void
    {
        $this->siteId = $siteId ?: Sites::getActive();
        $this->trigger = $trigger;
    }

    public function getActiveAgentProperty(): ?ChatAgent
    {
        return ChatAgent::where('site_id', $this->siteId)
            ->where('type', 'ai')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    public function getAvailableAgentsProperty(): array
    {
        return ChatAgent::where('site_id', $this->siteId)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('sort_order')
            ->get()
            ->map(function (ChatAgent $agent) {
                $avatarUrl = null;
                if ($agent->avatar) {
                    $avatarUrl = rescue(fn () => mediaHelper()->getSingleMedia($agent->avatar, 'medium')?->url, null, false);
                }

                $displayName = $agent->name ?: 'Medewerker';
                if ($agent->type === 'human') {
                    $displayName = explode(' ', trim($displayName))[0] ?: $displayName;
                }

                return [
                    'name' => $displayName,
                    'avatar' => $avatarUrl,
                    'type' => $agent->type,
                ];
            })
            ->all();
    }

    public function getMessagesProperty()
    {
        if (! $this->publicToken) {
            return collect();
        }
        $conversation = $this->conversation();

        return $conversation
            ? $conversation->messages()->with('agent')->where('is_internal', false)->whereIn('role', ['visitor', 'ai', 'human'])->get()
            : collect();
    }

    protected function conversation(): ?ChatConversation
    {
        return $this->publicToken
            ? ChatConversation::where('site_id', $this->siteId)->where('public_token', $this->publicToken)->first()
            : null;
    }

    /**
     * Post a bot question as a persisted AI message without triggering the AI job.
     */
    private function postBotMessage(ChatConversation $conversation, string $content): void
    {
        $conversation->messages()->create([
            'role' => 'ai',
            'agent_id' => $conversation->ai_agent_id,
            'content' => $content,
        ]);
    }

    protected function rules(): array
    {
        return [
            'newAttachments' => ['array', 'max:5'],
            'newAttachments.*' => ['file', 'max:10240', \Dashed\DashedLivechat\Support\AttachmentRules::clientImageOrPdf()],
        ];
    }

    public function removeAttachment(int $index): void
    {
        unset($this->newAttachments[$index]);
        $this->newAttachments = array_values($this->newAttachments);
    }

    public function sendMessage(ConversationManager $manager, InputGuard $guard): void
    {
        // Intercept contact-capture steps before normal flow.
        if ($this->contactStep === 'email') {
            $input = trim($this->draft);
            if ($input === '') {
                return;
            }

            $conversation = $this->conversation();
            if (! $conversation) {
                return;
            }

            if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
                $this->draft = '';
                $conversation->messages()->create([
                    'role' => 'visitor',
                    'content' => $input,
                ]);
                $conversation->visitor_email = $input;
                $conversation->save();

                $this->postBotMessage($conversation, 'Dank je! En wat is je naam?');
                $this->contactStep = 'name';

                return;
            }

            // Geen e-mailadres? Niet aandringen: iemand wil dat misschien niet
            // geven. We stoppen met vragen en behandelen de invoer hieronder als
            // een gewone vraag (valt door naar de normale flow).
            $this->contactStep = null;
            $this->contactDismissed = true;
        }

        if ($this->contactStep === 'name') {
            $name = trim($this->draft);
            if ($name === '') {
                return;
            }
            $this->draft = '';

            $conversation = $this->conversation();
            if (! $conversation) {
                return;
            }

            $conversation->messages()->create([
                'role' => 'visitor',
                'content' => $name,
            ]);
            $conversation->visitor_name = $name;
            $conversation->save();

            $this->postBotMessage($conversation, "Dank je, {$name}! Waar kan ik je verder mee helpen?");
            $this->contactStep = null;

            return;
        }

        // Normal flow.
        $text = trim($this->draft);
        $hasFiles = ! empty($this->newAttachments);
        if ($text === '' && ! $hasFiles) {
            return;
        }
        if ($hasFiles) {
            $this->validate();
        }

        // Rate-limit per conversatie/sessie.
        $key = 'chat:' . ($this->publicToken ?: request()->ip());
        if (RateLimiter::tooManyAttempts($key, (int) config('dashed-livechat.rate_limit_per_minute', 15))) {
            $this->addError('draft', 'Je stuurt te snel berichten. Wacht heel even.');

            return;
        }
        RateLimiter::hit($key, 60);

        $agent = ChatAgent::where('site_id', $this->siteId)->where('type', 'ai')->where('is_active', true)->orderBy('sort_order')->first();

        // Geen actieve AI-agent? Dan is dit een mensen-bemande chat. Het gesprek
        // start alleen als er nu medewerkers "aan staan" (binnen openingstijden,
        // of buiten openingstijden indien zo ingesteld).
        $humanOnly = ! $agent;
        if ($humanOnly && ! app(ChatAvailability::class)->isStaffed($this->siteId)) {
            $this->addError('draft', 'Chat is momenteel niet beschikbaar.');

            return;
        }

        $conversation = $manager->findOrCreate($this->siteId, $this->publicToken, [
            'ai_agent_id' => $agent?->id,
            'mode' => $humanOnly ? 'waiting_human' : 'ai',
            'started_url' => url()->previous(),
            'ip_hash' => hash('sha256', request()->ip() . config('app.key')),
            'locale' => app()->getLocale(),
        ]);
        $isNewConversation = $conversation->wasRecentlyCreated;
        $this->publicToken = $conversation->public_token;
        // Bezoeker is net actief (stuurde een bericht) → presence verversen.
        $this->touchVisitorActivity($conversation);

        // Guardrail laag 1.
        $check = $guard->check($text);
        $visitorMessage = $manager->addVisitorMessage($conversation, $text, $this->newAttachments);
        $this->draft = '';
        $this->newAttachments = [];

        if ($check->blocked) {
            $conversation->events()->create(['type' => 'guardrail_block', 'payload' => ['reason' => $check->reason]]);
            if ($agent) {
                $conversation->messages()->create([
                    'role' => 'ai', 'agent_id' => $agent->id,
                    'content' => 'Daar kan ik je niet mee helpen. Ik beantwoord alleen vragen over deze website. Waar kan ik je wel mee van dienst zijn?',
                ]);
            }

            return;
        }

        // Mensen-bemande chat (geen AI): de medewerkers handelen af. Notificeer
        // eenmalig bij een nieuw gesprek; vervolgberichten pushen al via
        // ConversationManager::addVisitorMessage.
        if ($humanOnly) {
            if ($isNewConversation) {
                app(\Dashed\DashedLivechat\Services\HandoffService::class)->startHumanChat($conversation);
            }

            return;
        }

        // De e-mailvraag komt niet hier, maar na ~15s inactiviteit (zie
        // maybeAskForEmail via pollReply), zodat het antwoord eerst komt en de
        // vervolgvraag niet wordt onderbroken.
        if ($conversation->mode !== 'human') {
            $delay = (int) ($this->activeAgent?->ai_reply_delay_seconds ?? 0);
            if (config('dashed-livechat.streaming', false)) {
                $this->streamUrl = route('dashed-livechat.stream', $this->publicToken);
                $this->awaitingReply = $delay === 0;
            } else {
                GenerateAiReplyJob::dispatch($conversation->id, $visitorMessage->id)
                    ->delay(now()->addSeconds($delay));
                $this->awaitingReply = $delay === 0;
            }
        }
    }

    public function pollReply(): void
    {
        $conversation = $this->conversation();
        $this->recomputeAwaitingReply($conversation);

        if ($conversation) {
            $this->touchVisitorActivity($conversation);
            $this->maybeAskForEmail($conversation);
        }
    }

    /**
     * Bepaalt of de AI-typindicator getoond moet worden. Wordt bij elke
     * roundtrip (poll/verzenden/refresh) opnieuw berekend zodat de indicator
     * direct verdwijnt zodra het AI-antwoord binnen is. Alleen in AI-modus en
     * alleen kort na een bezoekersbericht.
     */
    protected function recomputeAwaitingReply(?ChatConversation $conversation): void
    {
        $last = $conversation?->messages()->reorder()->latest('id')->first();
        $delay = (int) ($this->activeAgent?->ai_reply_delay_seconds ?? 0);

        // Toon "aan het typen" pas NA het wachtvenster (de AI begint dan te
        // genereren), en niet langer dan delay+60s — stil tijdens de delay.
        $this->awaitingReply = $conversation
            && $conversation->mode === 'ai'
            && $last
            && $last->role === 'visitor'
            && $last->created_at
            && $last->created_at->lte(now()->subSeconds($delay))
            && $last->created_at->gt(now()->subSeconds($delay + 60));
    }

    /**
     * Vraagt eenmalig om het e-mailadres zodra het gesprek ~15s stil ligt (geen
     * nieuw bericht). Zo kunnen we de bezoeker later per e-mail verder helpen als
     * die wegklikt. Niet verplicht: de bezoeker kan gewoon doorvragen (zie
     * sendMessage e-mailstap). De timer 'reset' vanzelf omdat elk nieuw bericht
     * de created_at van het laatste bericht verschuift.
     */
    protected function maybeAskForEmail(ChatConversation $conversation): void
    {
        if (
            $this->contactDismissed
            || $this->contactStep !== null
            || $conversation->visitor_email
        ) {
            return;
        }

        $last = $conversation->messages()->reorder()->latest('id')->first();
        if (! $last || ! $last->created_at) {
            return;
        }

        // Pas vragen na X seconden inactiviteit (geen nieuw bericht meer binnengekomen).
        $idle = (int) config('dashed-livechat.ask_email_after_seconds', 15);
        if ($last->created_at->gt(now()->subSeconds($idle))) {
            return;
        }

        $this->contactStep = 'email';
        $this->postBotMessage($conversation, 'Mag ik je e-mailadres? Dan kunnen we je ook later (per e-mail) verder helpen. Liever niet? Stel gerust gewoon je volgende vraag.');
    }

    /**
     * Stempelt dat de bezoeker zojuist actief was (de Livewire-poll fungeert als
     * heartbeat). Throttled tot ~1×/10s om bij elke poll geen DB-write te doen.
     * Sluit de tab/navigeert de bezoeker weg, dan stopt de poll en veroudert deze
     * tijd → agent-/AI-antwoorden gaan dan als e-mail (zie ConversationManager).
     */
    protected function touchVisitorActivity(?ChatConversation $conversation): void
    {
        if (! $conversation) {
            return;
        }
        // Koppel het beacon-token één keer aan het gesprek (voor de scherpe
        // aanwezigheidsbepaling), los van de activiteits-throttle.
        $token = trim((string) $this->visitorSessionToken);
        if ($token !== '' && $conversation->visitor_session_token !== $token) {
            $conversation->forceFill(['visitor_session_token' => $token])->save();
        }

        $last = $conversation->visitor_last_active_at;
        if ($last && $last->gt(now()->subSeconds(10))) {
            return;
        }
        $conversation->forceFill(['visitor_last_active_at' => now()])->save();
    }

    public function requestHuman(): void
    {
        $conversation = $this->conversation();
        if (! $conversation) {
            return;
        }

        $result = app(\Dashed\DashedLivechat\Services\HandoffService::class)->requestHandoff($conversation);
        $this->postBotMessage($conversation, $result['message'] ?? 'Ik haal er een collega bij, een moment geduld.');
    }

    /** Bezoeker beoordeelt het gesprek (CSAT); opgeslagen in conversation->meta. */
    public function rate(string $value): void
    {
        if (! in_array($value, ['up', 'down'], true)) {
            return;
        }

        $conversation = $this->conversation();
        if (! $conversation) {
            return;
        }

        $meta = $conversation->meta ?? [];
        if (! empty($meta['rating'])) {
            return;
        }

        $meta['rating'] = $value;
        $meta['rated_at'] = now()->toIso8601String();
        $conversation->meta = $meta;
        $conversation->save();
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    /**
     * Registreert dat de bezoeker de chat zojuist heeft gezien (leesbevestiging).
     * Throttled: schrijf alleen als er nog geen leestijd is of de laatste >2s
     * geleden was, zodat een poll niet elke ~1.5s een DB-write veroorzaakt.
     */
    public function markVisitorRead(): void
    {
        $conversation = $this->conversation();
        if (! $conversation) {
            return;
        }

        if (
            $conversation->visitor_read_at
            && $conversation->visitor_read_at->gt(now()->subSeconds(2))
        ) {
            return;
        }

        $conversation->forceFill(['visitor_read_at' => now()])->save();
    }

    // Feature B: restore conversation from localStorage token
    public function resumeConversation(?string $token): void
    {
        if (! $token) {
            return;
        }
        $conversation = ChatConversation::where('site_id', $this->siteId)
            ->where('public_token', $token)
            ->whereIn('status', ['active', 'inactive'])
            ->first();
        if ($conversation) {
            $this->publicToken = $conversation->public_token;
            $this->deriveContactStep($conversation);
        }
    }

    public function startNewChat(): void
    {
        $this->publicToken = null;
        $this->contactStep = null;
        $this->contactDismissed = false;
        $this->awaitingReply = false;
        $this->draft = '';
        $this->streamUrl = null;
    }

    // Feature C: dismiss the conversational contact capture
    public function dismissContact(): void
    {
        $this->contactDismissed = true;
        $this->contactStep = null;

        $conversation = $this->conversation();
        if ($conversation) {
            $this->postBotMessage($conversation, 'Geen probleem. Typ gerust verder.');
        }
    }

    /**
     * Re-derive contactStep from conversation state so refreshes keep the flow going.
     */
    private function deriveContactStep(ChatConversation $conversation): void
    {
        if ($this->contactDismissed || $conversation->visitor_email) {
            return;
        }
        if ($conversation->messages()->where('role', 'visitor')->exists()) {
            if ($this->contactStep === null) {
                // Check whether we already asked for name (visitor_email is set) or still need email.
                $this->contactStep = 'email';
            }
        }
    }

    public function placeholder(): string
    {
        // Render a static bubble that matches the real widget's configured position,
        // offset, and primary colour so there is no flash / side-swap when the real
        // component loads after paint.
        $siteId = $this->siteId ?: \Dashed\DashedCore\Classes\Sites::getActive();
        $cfg = \Dashed\DashedLivechat\Support\WidgetConfig::for($siteId);

        $position = $cfg['position'] ?? 'right';
        $offset   = (int) ($cfg['offset'] ?? 24);
        $primary  = $cfg['primary'] ?? '#111827';
        $radius   = (int) ($cfg['radius'] ?? 16);

        $side = $position === 'left' ? 'left' : 'right';

        return '<div aria-hidden="true" style="'
            . 'position:fixed;'
            . 'bottom:' . $offset . 'px;'
            . $side . ':' . $offset . 'px;'
            . 'width:56px;height:56px;'
            . 'border-radius:' . $radius . 'px;'
            . 'background-color:' . htmlspecialchars($primary, ENT_QUOTES) . ';'
            . 'z-index:9999;'
            . 'opacity:0;'
            . '"></div>';
    }

    public function render()
    {
        $cfg = \Dashed\DashedLivechat\Support\WidgetConfig::for($this->siteId);
        $showDelayNotice = app(ChatAvailability::class)->shouldShowDelayNotice($this->siteId);
        $agent = $this->activeAgent;

        $agentName = $agent?->name ?: null;
        $agentGreeting = $agent?->greeting ?: $cfg['greeting'];

        if ($agent?->avatar) {
            $agentAvatarUrl = rescue(fn () => mediaHelper()->getSingleMedia($agent->avatar, 'medium')?->url, null, false);
        } else {
            $agentAvatarUrl = $cfg['avatar'];
        }

        // Met wie chat je nu? In AI-modus de AI-agent, anders de mens/collega.
        $conversation = $this->conversation();
        $this->recomputeAwaitingReply($conversation);

        // Id van het laatste bericht; via @entangle reactief in Alpine zodat de
        // widget naar onder scrollt zodra er een bericht bijkomt.
        $this->lastMessageId = (int) ($this->messages->last()?->id ?? 0);

        $lastIncoming = $this->messages->where('role', '!=', 'visitor')->last();
        $this->lastMessagePreview = $lastIncoming
            ? \Illuminate\Support\Str::limit(trim(strip_tags((string) $lastIncoming->content)), 90)
            : '';

        $mode = $conversation?->mode ?? 'ai';
        $partnerType = 'ai';
        $partnerName = $agentName ?: ($cfg['title'] ?: 'Assistent');

        if ($mode === 'waiting_human') {
            $partnerType = 'waiting';
            $partnerName = 'Een collega komt eraan…';
        } elseif ($mode === 'human') {
            $partnerType = 'human';
            $assigned = $conversation?->assigned_agent_id ? ChatAgent::find($conversation->assigned_agent_id) : null;
            $partnerName = $assigned?->name ? (explode(' ', trim($assigned->name))[0] ?: $assigned->name) : 'Medewerker';
        }

        $humanAvailable = collect($this->availableAgents)->contains(fn ($a) => ($a['type'] ?? null) === 'human');

        // Foto van degene met wie je nu praat (voor de header): de toegewezen
        // medewerker in mensmodus, anders de AI-agent.
        $partnerAvatarUrl = $agentAvatarUrl;
        if ($partnerType === 'human' && isset($assigned) && $assigned?->avatar) {
            $partnerAvatarUrl = rescue(fn () => mediaHelper()->getSingleMedia($assigned->avatar, 'medium')?->url, null, false) ?: $agentAvatarUrl;
        }

        // Foto per bericht (afzender), gededupliceerd per agent zodat we media
        // niet voor elk bericht opnieuw opvragen. Bezoekersberichten krijgen geen
        // foto; AI/medewerker-berichten vallen terug op de agent-avatar.
        $avatarByAgent = [];
        $messageAvatars = [];
        foreach ($this->messages as $widgetMessage) {
            if ($widgetMessage->role === 'visitor') {
                continue;
            }
            $agentId = $widgetMessage->agent_id;
            if ($agentId && ! array_key_exists($agentId, $avatarByAgent)) {
                $avatarByAgent[$agentId] = $widgetMessage->agent?->avatar
                    ? rescue(fn () => mediaHelper()->getSingleMedia($widgetMessage->agent->avatar, 'medium')?->url, null, false)
                    : null;
            }
            $messageAvatars[$widgetMessage->id] = ($agentId ? $avatarByAgent[$agentId] : null) ?: $agentAvatarUrl;
        }

        return view('dashed-livechat::widget.widget', [
            'messages' => $this->messages,
            'siteId' => $this->siteId,
            'awaitingReply' => $this->awaitingReply,
            'trigger' => $this->trigger,
            'streamUrl' => $this->streamUrl,
            'agentName' => $agentName,
            'agentGreeting' => $agentGreeting,
            'agentAvatarUrl' => $agentAvatarUrl,
            'availableAgents' => $this->availableAgents,
            'contactStep' => $this->contactStep,
            'rating' => $conversation?->meta['rating'] ?? null,
            'canRate' => $conversation && empty($conversation->meta['rating']) && $this->messages->where('role', 'ai')->isNotEmpty(),
            'newMessageIndicator' => $cfg['new_message_indicator'] ?? 'badge',
            'partnerName' => $partnerName,
            'partnerType' => $partnerType,
            'partnerAvatarUrl' => $partnerAvatarUrl,
            'messageAvatars' => $messageAvatars,
            'chatMode' => $mode,
            // Pas tonen zodra het gesprek echt loopt (minstens één bericht),
            // dus niet al in het welkomstscherm.
            'canRequestHuman' => $mode === 'ai' && $humanAvailable && $this->messages->isNotEmpty(),
            // Buiten openingstijden, maar de chat is bemand: melding dat een
            // reactie langer kan duren.
            'showDelayNotice' => $showDelayNotice,
            'delayNotice' => $cfg['delay_notice'] ?? null,
        ]);
    }
}
