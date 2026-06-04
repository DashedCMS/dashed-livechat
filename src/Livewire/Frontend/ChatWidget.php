<?php

// src/Livewire/Frontend/ChatWidget.php

namespace Dashed\DashedLivechat\Livewire\Frontend;

use Livewire\Component;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatAgent;
use Illuminate\Support\Facades\RateLimiter;
use Dashed\DashedLivechat\Guardrails\InputGuard;
use Dashed\DashedLivechat\Jobs\GenerateAiReplyJob;
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

    protected function conversation()
    {
        return $this->publicToken
            ? \Dashed\DashedLivechat\Models\ChatConversation::where('site_id', $this->siteId)->where('public_token', $this->publicToken)->first()
            : null;
    }

    public function sendMessage(ConversationManager $manager, InputGuard $guard): void
    {
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
        ]);
    }
}
