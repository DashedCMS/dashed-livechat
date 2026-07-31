<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatUnansweredQuestion extends Model
{
    protected $table = 'dashed__chat_unanswered_questions';

    protected $guarded = [];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /**
     * Legt een onbeantwoorde bezoekersvraag vast voor de review-wachtrij.
     * Best-effort: faalt nooit hard (mag het gesprek niet breken).
     */
    public static function capture(ChatConversation $conversation, string $reason, ?string $question = null): void
    {
        rescue(function () use ($conversation, $reason, $question): void {
            $text = trim((string) ($question ?? self::lastVisitorMessage($conversation)));
            if ($text === '') {
                return;
            }

            self::create([
                'site_id' => $conversation->site_id,
                'chat_conversation_id' => $conversation->id,
                'question' => $text,
                'reason' => $reason,
                'status' => 'open',
            ]);
        });
    }

    private static function lastVisitorMessage(ChatConversation $conversation): string
    {
        $message = $conversation->messages()
            ->where('role', 'visitor')
            ->where('is_internal', false)
            ->reorder('id', 'desc')
            ->first();

        return (string) ($message->content ?? '');
    }
}
