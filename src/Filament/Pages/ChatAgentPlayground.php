<?php

// src/Filament/Pages/ChatAgentPlayground.php

namespace Dashed\DashedLivechat\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Ai\ChatAgentRunner;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\ConversationManager;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;

/**
 * Sandbox waarin een admin live met een gekozen AI-agent chat, geïsoleerd van
 * echte bezoekersgesprekken (`is_sandbox = true`), met per beurt de tool-trace
 * (welke tools de AI aanriep, met welke input/output) zichtbaar. Bedoeld om
 * agent-configuratie (persona/guardrails/tools) te testen vóór livegang.
 */
class ChatAgentPlayground extends Page
{
    use HiddenWhenChatDisabled;

    protected static string|UnitEnum|null $navigationGroup = 'Chat';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationLabel = 'Agent-testomgeving';

    protected static ?string $title = 'Agent-testomgeving';

    protected static ?int $navigationSort = 5;

    protected string $view = 'dashed-livechat::filament.agent-playground';

    public string $siteId = 'main';

    /** @var array<int, array{id:int,name:string}> */
    public array $agents = [];

    public ?int $selectedAgentId = null;

    public ?string $sandboxToken = null;

    public string $message = '';

    /** @var array<int, array{role:string, content:string, tool_trace:array}> */
    public array $turns = [];

    public ?string $errorMessage = null;

    public bool $sending = false;

    public function mount(): void
    {
        $this->siteId = Sites::getActive() ?: 'main';
        $this->agents = ChatAgent::where('site_id', $this->siteId)
            ->where('type', 'ai')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(fn (ChatAgent $agent) => ['id' => $agent->id, 'name' => $agent->name])
            ->values()
            ->all();

        $this->selectedAgentId = $this->agents[0]['id'] ?? null;

        $this->loadTurnsFromExistingSandbox();
    }

    public function getSelectedAgentProperty(): ?ChatAgent
    {
        if (! $this->selectedAgentId) {
            return null;
        }

        return ChatAgent::find($this->selectedAgentId);
    }

    /** Eén-regel uitleg bij de guardrail-modus van de geselecteerde agent. */
    public function getGuardrailExplanationProperty(): ?string
    {
        $agent = $this->getSelectedAgentProperty();
        if (! $agent) {
            return null;
        }

        return $agent->guardrail_mode === 'strict'
            ? 'Streng: een extra controle vangt off-topic vragen harder af (iets duurder).'
            : 'Standaard: normale afweging zonder extra off-topic-controle.';
    }

    /**
     * Herstelt eerder gevoerde turns wanneer de agentkeuze wisselt maar er al
     * een sandbox-gesprek voor deze agent bestond in deze Livewire-sessie.
     */
    protected function loadTurnsFromExistingSandbox(): void
    {
        if (! $this->sandboxToken) {
            return;
        }

        $conversation = ChatConversation::where('site_id', $this->siteId)
            ->where('public_token', $this->sandboxToken)
            ->where('is_sandbox', true)
            ->first();

        if (! $conversation) {
            return;
        }

        $this->turns = $conversation->messages()
            ->orderBy('id')
            ->get()
            ->filter(fn ($m) => in_array($m->role, ['visitor', 'ai'], true))
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => (string) $m->content,
                'tool_trace' => (array) ($m->tool_calls ?? []),
            ])
            ->values()
            ->all();
    }

    public function updatedSelectedAgentId(): void
    {
        // Elke agent krijgt zijn eigen sandbox-conversatie; bij wisselen starten
        // we schoon zodat de trace niet tussen agents door elkaar loopt.
        $this->resetSandbox();
    }

    public function send(): void
    {
        $content = trim($this->message);
        if ($content === '' || ! $this->selectedAgentId) {
            return;
        }

        $agent = ChatAgent::find($this->selectedAgentId);
        if (! $agent) {
            $this->errorMessage = 'Kies eerst een AI-agent.';

            return;
        }

        $this->sending = true;
        $this->errorMessage = null;

        try {
            $conversations = app(ConversationManager::class);

            $conversation = $conversations->findOrCreate($this->siteId, $this->sandboxToken, [
                'is_sandbox' => true,
                'ai_agent_id' => $agent->id,
                'visitor_name' => 'Sandbox',
                'mode' => 'ai',
            ]);

            $this->sandboxToken = $conversation->public_token;

            $conversations->addVisitorMessage($conversation, $content);
            $this->turns[] = ['role' => 'visitor', 'content' => $content, 'tool_trace' => []];
            $this->message = '';

            $reply = app(ChatAgentRunner::class)->run($conversation);

            $this->turns[] = [
                'role' => 'ai',
                'content' => (string) $reply->content,
                'tool_trace' => (array) ($reply->tool_calls ?? []),
            ];
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = 'De AI kon niet antwoorden. Probeer het opnieuw of controleer de agent-configuratie.';
        } finally {
            $this->sending = false;
        }
    }

    public function resetSandbox(): void
    {
        $this->sandboxToken = null;
        $this->turns = [];
        $this->message = '';
        $this->errorMessage = null;
    }
}
