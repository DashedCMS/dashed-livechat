<?php

// src/Http/Controllers/StreamChatReplyController.php

namespace Dashed\DashedLivechat\Http\Controllers;

use Throwable;
use Dashed\DashedLivechat\Ai\ChatAgentRunner;
use Dashed\DashedLivechat\Models\ChatConversation;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamChatReplyController
{
    public function __invoke(string $token, ChatAgentRunner $runner): StreamedResponse
    {
        $conversation = ChatConversation::where('public_token', $token)->firstOrFail();

        return response()->stream(function () use ($conversation, $runner) {
            // C1: mode guard — no AI call when a human agent is handling the conversation.
            if (in_array($conversation->mode, ['human', 'waiting_human'], true) || ! $conversation->aiAgent) {
                echo "event: done\ndata: {}\n\n";
                @ob_flush();
                @flush();

                return;
            }

            // C1: pending-guard — only run if the last message is a visitor message.
            // Re-hitting the URL without a new visitor message is a no-op.
            // reorder() clears the relationship's default orderBy so we can sort DESC cleanly.
            $last = $conversation->messages()->reorder()->orderByDesc('id')->first();
            if (! $last || $last->role !== 'visitor') {
                echo "event: done\ndata: {}\n\n";
                @ob_flush();
                @flush();

                return;
            }

            try {
                $runner->runStreaming(
                    $conversation,
                    // $onText: stream each delta to the browser
                    function (string $delta) {
                        echo 'data: ' . json_encode(['t' => $delta]) . "\n\n";
                        @ob_flush();
                        @flush();
                    },
                    // $onReset (I1): clear the browser's live buffer between tool turns
                    function () {
                        echo "event: reset\ndata: {}\n\n";
                        @ob_flush();
                        @flush();
                    }
                );
            } catch (Throwable $e) {
                // I2: emit a terminal error event so the EventSource always closes cleanly.
                echo "event: error\ndata: " . json_encode(['message' => 'Er ging iets mis.']) . "\n\n";
                @ob_flush();
                @flush();
            }

            echo "event: done\ndata: {}\n\n";
            @ob_flush();
            @flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
