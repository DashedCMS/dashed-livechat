<?php

namespace Dashed\DashedLivechat\Mail;

use Illuminate\Support\Collection;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Het hele gesprek, gemaild zodra het op inactief gaat en wij als laatste
 * reageerden. Reply-to is ons eigen adres zodat de bezoeker kan antwoorden,
 * en de knop hervat het gesprek in de widget (dat kan zolang het inactief is).
 */
class ConversationTranscriptMail extends ChatCustomerMail
{
    public function __construct(
        public ChatConversation $conversation,
        public ?string $replyToEmail = null,
    ) {
    }

    public function conversation(): ChatConversation
    {
        return $this->conversation;
    }

    public function envelope(): Envelope
    {
        $siteName = $this->siteName();

        return new Envelope(
            from: $this->fromAddress(),
            subject: 'Je gesprek met ' . $siteName,
            replyTo: $this->replyToEmail ? [new Address($this->replyToEmail, $siteName)] : [],
        );
    }

    protected function messagesToShow(): Collection
    {
        return $this->conversation->messages()
            ->with('agent')
            ->whereIn('role', ['visitor', 'ai', 'human'])
            ->where('is_internal', false)
            ->orderBy('id')
            ->get();
    }

    protected function title(): string
    {
        return 'Je gesprek met ' . $this->siteName();
    }

    protected function intro(): string
    {
        return 'Hierbij een overzicht van je chatgesprek, zodat je het nog eens rustig kunt teruglezen.';
    }

    protected function replyHint(): ?string
    {
        return $this->replyToEmail
            ? 'Nog een vraag? Reageer gewoon op deze e-mail, dan pakken we het daar op.'
            : null;
    }
}
