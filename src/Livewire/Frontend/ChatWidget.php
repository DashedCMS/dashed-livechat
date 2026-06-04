<?php

// src/Livewire/Frontend/ChatWidget.php

namespace Dashed\DashedLivechat\Livewire\Frontend;

use Livewire\Component;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatAgent;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedLivechat\Guardrails\InputGuard;
use Dashed\DashedLivechat\Jobs\GenerateAiReplyJob;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\ConversationManager;

class ChatWidget extends Component
{
    public string $siteId = '';
    public ?string $publicToken = null;
    public string $draft = '';
    public bool $open = false;
    public bool $awaitingReply = false;
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
            ? $conversation->messages()->where('is_internal', false)->whereIn('role', ['visitor', 'ai', 'human'])->get()
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

    public function sendMessage(ConversationManager $manager, InputGuard $guard): void
    {
        // Intercept contact-capture steps before normal flow.
        if ($this->contactStep === 'email') {
            $email = trim($this->draft);
            if ($email === '') {
                return;
            }
            $this->draft = '';

            $conversation = $this->conversation();
            if (! $conversation) {
                return;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->postBotMessage($conversation, 'Dat lijkt geen geldig e-mailadres. Wil je het nog eens proberen? Of klik op Overslaan.');

                return;
            }

            // Persist visitor answer + update conversation.
            $conversation->messages()->create([
                'role' => 'visitor',
                'content' => $email,
            ]);
            $conversation->visitor_email = $email;
            $conversation->save();

            $this->postBotMessage($conversation, 'Dank je! En wat is je naam?');
            $this->contactStep = 'name';

            return;
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
        if ($text === '') {
            return;
        }

        // Rate-limit per conversatie/sessie.
        $key = 'chat:' . ($this->publicToken ?: request()->ip());
        if (RateLimiter::tooManyAttempts($key, (int) config('dashed-livechat.rate_limit_per_minute', 15))) {
            $this->addError('draft', 'Je stuurt te snel berichten. Wacht heel even.');

            return;
        }
        RateLimiter::hit($key, 60);

        $agent = ChatAgent::where('site_id', $this->siteId)->where('type', 'ai')->where('is_active', true)->orderBy('sort_order')->first();
        if (! $agent) {
            $this->addError('draft', 'Chat is momenteel niet beschikbaar.');

            return;
        }

        $conversation = $manager->findOrCreate($this->siteId, $this->publicToken, [
            'ai_agent_id' => $agent->id,
            'started_url' => url()->previous(),
            'ip_hash' => hash('sha256', request()->ip() . config('app.key')),
            'locale' => app()->getLocale(),
        ]);
        $this->publicToken = $conversation->public_token;

        // Guardrail laag 1.
        $check = $guard->check($text);
        $manager->addVisitorMessage($conversation, $text);
        $this->draft = '';

        if ($check->blocked) {
            $conversation->events()->create(['type' => 'guardrail_block', 'payload' => ['reason' => $check->reason]]);
            $conversation->messages()->create([
                'role' => 'ai', 'agent_id' => $agent->id,
                'content' => 'Daar kan ik je niet mee helpen. Ik beantwoord alleen vragen over deze website. Waar kan ik je wel mee van dienst zijn?',
            ]);

            return;
        }

        // Trigger e-mail ask after first visitor message (if not dismissed and no email yet).
        if (
            ! $this->contactDismissed
            && $this->contactStep === null
            && ! $conversation->visitor_email
            && $conversation->messages()->where('role', 'visitor')->count() === 1
        ) {
            $this->contactStep = 'email';
            $this->postBotMessage($conversation, 'Mag ik je e-mailadres? Dan kunnen we je ook later nog verder helpen.');

            return;
        }

        if ($conversation->mode !== 'human') {
            $this->awaitingReply = true;
            if (config('dashed-livechat.streaming', false)) {
                $this->streamUrl = route('dashed-livechat.stream', $this->publicToken);
            } else {
                GenerateAiReplyJob::dispatch($conversation->id);
            }
        }
    }

    public function pollReply(): void
    {
        $conversation = $this->conversation();
        if ($conversation) {
            $last = $conversation->messages()->latest('id')->first();
            $this->awaitingReply = $last && $last->role === 'visitor';
        }
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    // Feature B: restore conversation from localStorage token
    public function resumeConversation(?string $token): void
    {
        if (! $token) {
            return;
        }
        $conversation = ChatConversation::where('site_id', $this->siteId)
            ->where('public_token', $token)
            ->where('status', 'active')
            ->first();
        if ($conversation) {
            $this->publicToken = $conversation->public_token;
            $this->deriveContactStep($conversation);
        }
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

    public function render()
    {
        $cfg = \Dashed\DashedLivechat\Support\WidgetConfig::for($this->siteId);
        $agent = $this->activeAgent;

        $agentName = $agent?->name ?: null;
        $agentGreeting = $agent?->greeting ?: $cfg['greeting'];

        if ($agent?->avatar) {
            $agentAvatarUrl = rescue(fn () => mediaHelper()->getSingleMedia($agent->avatar, 'medium')?->url, null, false);
        } else {
            $agentAvatarUrl = $cfg['avatar'];
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
        ]);
    }
}
