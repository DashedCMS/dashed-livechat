<?php

namespace Dashed\DashedLivechat\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\ChatConversation;

class ConversationTranscriptMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ?string $replyToEmail = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $name = config('app.name');
        $siteId = $this->conversation->site_id;
        $fromEmail = Customsetting::get('site_from_email', $siteId) ?: config('mail.from.address');
        $fromName = Customsetting::get('site_name', $siteId) ?: config('mail.from.name');

        return new Envelope(
            from: new Address($fromEmail, $fromName),
            subject: 'Je gesprek met ' . $name,
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail, $name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'dashed-livechat::mail.transcript',
            with: [
                'conversation' => $this->conversation,
                'messages' => $this->conversation->messages()
                    ->with('agent')
                    ->whereIn('role', ['visitor', 'ai', 'human'])
                    ->where('is_internal', false)
                    ->orderBy('id')
                    ->get(),
                'businessName' => config('app.name'),
                'replyToEmail' => $this->replyToEmail,
            ],
        );
    }
}
