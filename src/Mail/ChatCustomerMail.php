<?php

namespace Dashed\DashedLivechat\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Support\ChatMailPresenter;

/**
 * Gedeelde basis van de mails die de chat naar de bezoeker stuurt. Ze gaan
 * alle in dashed-core::emails.layout (logo, mailkleur, voettekst van de site)
 * met daarin het gesprek zoals de widget het toont.
 */
abstract class ChatCustomerMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    abstract public function conversation(): ChatConversation;

    /** @return Collection<int, ChatMessage> */
    abstract protected function messagesToShow(): Collection;

    abstract protected function title(): string;

    abstract protected function intro(): string;

    protected function replyHint(): ?string
    {
        return null;
    }

    protected function siteName(): string
    {
        return (string) (Customsetting::get('site_name', $this->conversation()->site_id) ?: config('app.name'));
    }

    protected function fromAddress(): Address
    {
        $siteId = $this->conversation()->site_id;

        return new Address(
            Customsetting::get('site_from_email', $siteId) ?: config('mail.from.address'),
            Customsetting::get('site_name', $siteId) ?: config('mail.from.name'),
        );
    }

    public function content(): Content
    {
        $data = ChatMailPresenter::for($this->conversation(), $this->messagesToShow());

        $blocks = [
            view('dashed-livechat::mail.blocks.intro', [
                'title' => $this->title(),
                'intro' => $this->intro(),
            ])->render(),
            view('dashed-livechat::mail.blocks.conversation', $data)->render(),
            view('dashed-livechat::mail.blocks.actions', $data + ['replyHint' => $this->replyHint()])->render(),
        ];

        return new Content(
            view: 'dashed-core::emails.layout',
            with: $data['branding'] + ['blocks' => $blocks],
        );
    }
}
