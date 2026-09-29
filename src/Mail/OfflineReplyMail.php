<?php

namespace Dashed\DashedLivechat\Mail;

use Illuminate\Support\Collection;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Stuurt één agent-/AI-antwoord naar een bezoeker die de chat heeft verlaten,
 * met de laatste vraag van de bezoeker erboven als context en een knop om het
 * gesprek te hervatten (via de public_token in de URL).
 */
class OfflineReplyMail extends ChatCustomerMail
{
    /**
     * Let op de naam: niet $message. Illuminate\Mail\Mailer::send() doet
     * $data['message'] = $this->createMessage(), dus view-data met die sleutel
     * wordt bij het renderen altijd overschreven door de mail-Message zelf.
     */
    public function __construct(
        public ChatConversation $conversation,
        public ChatMessage $chatMessage,
    ) {
    }

    public function conversation(): ChatConversation
    {
        return $this->conversation;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->fromAddress(),
            subject: 'Nieuw bericht van ' . $this->siteName(),
        );
    }

    /** De laatste bezoekersvraag vóór dit antwoord, en het antwoord zelf. */
    protected function messagesToShow(): Collection
    {
        $question = $this->conversation->messages()
            ->where('role', 'visitor')
            ->where('is_internal', false)
            ->where('id', '<', $this->chatMessage->id)
            ->reorder('id', 'desc') // de relatie sorteert zelf al oplopend
            ->first();

        $this->chatMessage->loadMissing('agent');

        return collect(array_filter([$question, $this->chatMessage]));
    }

    protected function title(): string
    {
        return 'Nieuw bericht van ' . $this->siteName();
    }

    protected function intro(): string
    {
        return 'Je was net weg uit de chat, dus we sturen je het antwoord ook even per e-mail.';
    }

    protected function replyHint(): ?string
    {
        return 'Of reageer gewoon op deze e-mail, dan pakken we het daar op.';
    }
}
