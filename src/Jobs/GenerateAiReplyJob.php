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

    public function __construct(public int $conversationId)
    {
    }

    public function handle(ChatAgentRunner $runner): void
    {
        $conversation = ChatConversation::find($this->conversationId);
        if (! $conversation || in_array($conversation->mode, ['human', 'waiting_human'], true) || ! $conversation->aiAgent) {
            return;
        }

        try {
            $runner->run($conversation);
        } catch (\Throwable $e) {
            Log::warning('chat: AI-antwoord mislukt', ['conversation' => $this->conversationId, 'error' => $e->getMessage()]);
            $conversation->messages()->create([
                'role' => MessageRole::Ai->value,
                'agent_id' => $conversation->ai_agent_id,
                'content' => 'Sorry, er ging iets mis aan onze kant. Probeer het zo nog eens of laat je vraag achter.',
            ]);
            $conversation->forceFill(['last_message_at' => now()])->save();
        }
    }
}
