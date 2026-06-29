<?php

namespace Dashed\DashedLivechat\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Stuurt één agent-/AI-antwoord naar een bezoeker die de chat heeft verlaten,
 * met een knop om het gesprek te hervatten (via de public_token in de URL).
 */
class OfflineReplyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ChatMessage $message,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nieuw bericht van ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'dashed-livechat::mail.offline-reply',
            with: [
                'conversation' => $this->conversation,
                'message' => $this->message,
                'businessName' => config('app.name'),
                'agentName' => $this->message->agent?->name,
                'resumeUrl' => $this->resumeUrl(),
            ],
        );
    }

    /**
     * Bouwt de hervat-URL: de pagina waar het gesprek begon (of de site-URL) met
     * de public_token als query-parameter, die de widget oppikt om te hervatten.
     */
    private function resumeUrl(): string
    {
        $base = $this->conversation->started_url ?: config('app.url');
        $separator = str_contains((string) $base, '?') ? '&' : '?';

        return $base . $separator . 'dashed_chat=' . $this->conversation->public_token;
    }
}
