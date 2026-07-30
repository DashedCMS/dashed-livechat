<?php

// src/Mail/HandoffNotificationMail.php

namespace Dashed\DashedLivechat\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\ChatConversation;

class HandoffNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public ChatConversation $conversation, public ?string $reason = null)
    {
    }

    public function build()
    {
        $url = rescue(fn () => route('filament.dashed.resources.chat-conversations.view', ['record' => $this->conversation->id]), null, false);

        $notification = '<p>Een bezoeker vraagt om een medewerker in een chatgesprek.</p>';

        if ($this->reason !== null && $this->reason !== '') {
            $notification .= '<p>Reden: ' . e($this->reason) . '</p>';
        }

        $notification .= '<p>Site: ' . e($this->conversation->site_id) . '</p>';

        if ($url !== null) {
            $notification .= '<p><a href="' . e($url) . '" style="display: inline-block; padding: 10px 20px; background-color: #111827; color: #ffffff; border-radius: 6px; text-decoration: none; font-weight: bold;">Open het gesprek</a></p>';
        }

        $view = view()->exists(config('dashed-core.site_theme', 'dashed') . '.emails.notification')
            ? config('dashed-core.site_theme', 'dashed') . '.emails.notification'
            : 'dashed-core::emails.notification';

        return $this->view($view)
            ->from(
                Customsetting::get('site_from_email', $this->conversation->site_id) ?: config('mail.from.address'),
                Customsetting::get('site_name', $this->conversation->site_id) ?: config('mail.from.name')
            )
            ->subject('Een chatgesprek vraagt om een medewerker')
            ->with([
                'notification' => $notification,
            ]);
    }
}
