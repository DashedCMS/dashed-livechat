<?php

// src/Jobs/GenerateAiReplyJob.php

namespace Dashed\DashedLivechat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedLivechat\Enums\MessageRole;
use Dashed\DashedLivechat\Ai\ChatAgentRunner;
use Dashed\DashedLivechat\Models\ChatConversation;

class GenerateAiReplyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $conversationId, public ?int $triggerMessageId = null)
    {
    }

    public function handle(ChatAgentRunner $runner): void
    {
        $conversation = ChatConversation::find($this->conversationId);
        if (! $conversation || in_array($conversation->mode, ['human', 'waiting_human'], true) || ! $conversation->aiAgent) {
            return;
        }

        // Debounce + mens-voorrang: alleen antwoorden als het bericht waarvoor deze
        // job is gepland nog steeds het laatste (niet-interne) bericht is. Is er
        // sindsdien een nieuwer bezoekersbericht (debounce) of een mens/AI-antwoord,
        // dan stopt deze job — de job van het laatste bezoekersbericht handelt af.
        if ($this->triggerMessageId !== null) {
            $last = $conversation->messages()->where('is_internal', false)->reorder()->latest('id')->first();
            if (! $last || $last->id !== $this->triggerMessageId || $last->role !== MessageRole::Visitor->value) {
                return;
            }
        }

        try {
            $runner->run($conversation);
        } catch (\Throwable $e) {
            Log::warning('chat: AI-antwoord mislukt', ['conversation' => $this->conversationId, 'error' => $e->getMessage()]);

            // Echte foutmelding bewaren als intern systeembericht, zodat een
            // medewerker in het CMS kan zien wat er misging. is_internal => true
            // houdt dit verborgen voor de bezoeker (widget en API filteren hierop).
            $conversation->messages()->create([
                'role' => MessageRole::System->value,
                'is_internal' => true,
                'content' => sprintf(
                    "AI-antwoord mislukt: %s\n\n%s\nin %s:%d",
                    $e->getMessage(),
                    $e::class,
                    $e->getFile(),
                    $e->getLine(),
                ),
            ]);

            $conversation->messages()->create([
                'role' => MessageRole::Ai->value,
                'agent_id' => $conversation->ai_agent_id,
                'content' => 'Sorry, ik kan je vraag op dit moment niet goed beantwoorden. Ik haal er een collega bij die je verder helpt.',
            ]);

            app(\Dashed\DashedLivechat\Services\HandoffService::class)
                ->escalateForFailure($conversation, 'AI-antwoord mislukt');

            $conversation->forceFill(['last_message_at' => now()])->save();
        }
    }
}
