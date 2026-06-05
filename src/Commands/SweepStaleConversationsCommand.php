<?php

namespace Dashed\DashedLivechat\Commands;

use Illuminate\Console\Command;
use Dashed\DashedLivechat\Models\ChatConversation;

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
        $inactive = ChatConversation::query()
            ->where('status', 'active')
            ->whereNotNull('last_message_at')
            ->where('last_message_at', '<=', now()->subMinutes(15))
            ->update(['status' => 'inactive']);

        $this->info("Afgerond: {$closed}, inactief: {$inactive}.");

        return self::SUCCESS;
    }
}
