<?php

// src/Mail/HandoffNotificationMail.php

namespace Dashed\DashedLivechat\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
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

        return $this->subject('Een chatgesprek vraagt om een medewerker')
            ->view('dashed-livechat::mail.handoff', [
                'conversation' => $this->conversation,
                'reason' => $this->reason,
                'url' => $url,
            ]);
    }
}
