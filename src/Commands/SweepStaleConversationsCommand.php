<?php

namespace Dashed\DashedLivechat\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Dashed\DashedLivechat\Support\WidgetConfig;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Mail\ConversationTranscriptMail;

class SweepStaleConversationsCommand extends Command
{
    protected $signature = 'dashed-livechat:sweep-stale-conversations';

    protected $description = 'Markeert gesprekken inactief na 15 minuten en afgerond na 1 uur zonder reactie.';

    public function handle(): int
    {
        // Afgerond na 1 uur geen reactie (vanuit actief of inactief).
        $closed = ChatConversation::query()
            ->whereIn('status', ['active', 'inactive'])
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', now()->subHour())
            ->update(['status' => 'closed']);

        // Inactief na 15 minuten geen reactie (alleen vanuit actief).
        $becameInactive = ChatConversation::query()
            ->where('status', 'active')
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', now()->subMinutes(15))
            ->get();

        foreach ($becameInactive as $conversation) {
            $conversation->forceFill(['status' => 'inactive'])->save();
            $this->mailTranscriptIfNeeded($conversation);
        }

        $this->info('Afgerond: ' . $closed . ', inactief: ' . $becameInactive->count() . '.');

        return self::SUCCESS;
    }

    /**
     * Mailt het gesprek naar de bezoeker zodra het op inactief gaat, mits er een
     * e-mailadres bekend is en WIJ (AI/medewerker) als laatste reageerden.
     * Reply-to is ons eigen e-mailadres, zodat de bezoeker kan antwoorden.
     */
    protected function mailTranscriptIfNeeded(ChatConversation $conversation): void
    {
        $email = trim((string) $conversation->visitor_email);
        if ($email === '') {
            return;
        }

        $last = $conversation->messages()
            ->where('is_internal', false)
            ->whereIn('role', ['visitor', 'ai', 'human'])
            ->orderByDesc('id')
            ->first();

        // Alleen mailen als ons bericht het laatste was (bezoeker reageerde niet).
        if (! $last || $last->role === 'visitor') {
            return;
        }

        $replyTo = WidgetConfig::for($conversation->site_id)['email'] ?? null;

        rescue(fn () => Mail::to($email)->send(
            new ConversationTranscriptMail($conversation, $replyTo)
        ), null, false);
    }
}
