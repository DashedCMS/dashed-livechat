<?php

// src/Ai/ChatAgentRunner.php

namespace Dashed\DashedLivechat\Ai;

use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Services\ConversationManager;

class ChatAgentRunner
{
    public function __construct(
        private ToolRegistry $registry,
        private SystemPromptBuilder $prompts,
        private ConversationManager $conversations,
    ) {
    }

    public function run(ChatConversation $conversation): ChatMessage
    {
        return $this->loop($conversation, null, null);
    }

    public function runStreaming(ChatConversation $conversation, callable $onText, ?callable $onReset = null): ChatMessage
    {
        return $this->loop($conversation, $onText, $onReset);
    }

    /**
     * Shared tool-loop used by both run() and runStreaming().
     *
     * @param callable|null $onText   Streaming text callback; null for polling path.
     * @param callable|null $onReset  Called before each turn after the first (streaming only); emits a reset signal to the browser.
     */
    protected function loop(ChatConversation $conversation, ?callable $onText, ?callable $onReset): ChatMessage
    {
        /** @var ChatAgent $agent */
        $agent = $conversation->aiAgent;
        $tools = $this->registry->forAgent($agent);
        $toolSchema = $this->registry->anthropicSchema($tools);

        $summary = app(\Dashed\DashedLivechat\Services\ConversationSummarizer::class)->summaryFor($conversation);
        $messages = $this->buildHistory($conversation, $summary !== null);
        $system = $this->prompts->build($agent, $conversation);
        if ($summary) {
            $system .= "\n\nSamenvatting van het eerdere gesprek (context):\n" . $summary;
        }

        $maxIterations = (int) config('dashed-livechat.max_tool_iterations', 5);
        $toolTrace = [];
        $tokensIn = 0;
        $tokensOut = 0;

        for ($i = 0; $i < $maxIterations; $i++) {
            // I1: before each turn after the first, reset the browser's live buffer
            // so pre-tool "thinking" text is not concatenated with the final answer.
            if ($i > 0 && $onReset !== null) {
                ($onReset)();
            }

            if ($onText !== null) {
                $response = LivechatAi::requireClaude()->streamMessages($messages, [
                    'system' => $system,
                    'tools' => $toolSchema,
                    'model' => $agent->model,
                    'temperature' => $agent->temperature,
                    'max_tokens' => 1024,
                ], $onText);
            } else {
                $response = LivechatAi::requireClaude()->messages($messages, [
                    'system' => $system,
                    'tools' => $toolSchema,
                    'model' => $agent->model,
                    'temperature' => $agent->temperature,
                    'max_tokens' => 1024,
                ]);
            }

            if ($response === null) {
                return $this->conversations->addAiMessage(
                    $conversation,
                    $agent,
                    'Sorry, ik kon je vraag niet volledig afronden. Wil je het anders formuleren?',
                    $toolTrace,
                    $tokensIn,
                    $tokensOut
                );
            }

            $tokensIn += $response['usage']['input_tokens'] ?? 0;
            $tokensOut += $response['usage']['output_tokens'] ?? 0;

            $content = $response['content'] ?? [];

            // Voeg de assistant-turn toe aan de messages (volledige content-blokken).
            $messages[] = ['role' => 'assistant', 'content' => $content];

            if (($response['stop_reason'] ?? null) !== 'tool_use') {
                $text = $this->extractText($content);

                return $this->conversations->addAiMessage($conversation, $agent, $text, $toolTrace, $tokensIn, $tokensOut);
            }

            // Voer elke tool_use uit en bouw één user-turn met tool_results.
            $toolResults = [];
            foreach ($content as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }
                $tool = $this->registry->get($block['name']);
                $result = $tool
                    ? $tool->handle($block['input'] ?? [], $conversation)
                    : ['error' => 'unknown_tool'];

                $toolTrace[] = ['name' => $block['name'], 'input' => $this->maskInput($block['input'] ?? [])];
                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => json_encode($result),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        // Loop-limiet bereikt: geef een nette fallback.
        return $this->conversations->addAiMessage(
            $conversation,
            $agent,
            'Sorry, ik kon je vraag niet volledig afronden. Wil je het anders formuleren?',
            $toolTrace,
            $tokensIn,
            $tokensOut
        );
    }

    /** @return array<int, array{role:string, content:mixed}> */
    protected function buildHistory(ChatConversation $conversation, bool $hasSummary = false): array
    {
        $limit = $hasSummary
            ? (int) config('dashed-livechat.summary_keep_recent', 10)
            : (int) config('dashed-livechat.history_limit', 20);

        // reorder() overschrijft de default orderBy('id') ASC van de
        // messages()-relatie; anders blijft de query ASC en draait reverse()
        // de geschiedenis juist verkeerd om (Claude kreeg het gesprek dan
        // achterstevoren en reageerde op het eerste bericht).
        return $conversation->messages()
            ->whereIn('role', ['visitor', 'ai'])
            ->where('is_internal', false)
            ->reorder('id', 'desc')->limit($limit)->get()->reverse()
            ->map(fn (ChatMessage $m) => [
                'role' => $m->role === 'visitor' ? 'user' : 'assistant',
                'content' => $m->content,
            ])->values()->all();
    }

    protected function extractText(array $content): string
    {
        return collect($content)
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");
    }

    protected function maskInput(array $input): array
    {
        foreach (['orderNumber', 'email'] as $sensitive) {
            if (array_key_exists($sensitive, $input)) {
                $input[$sensitive] = \Dashed\DashedLivechat\Services\OrderVerification::mask((string) $input[$sensitive]);
            }
        }

        return $input;
    }
}
