<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Berekent de wachtrij-positie en een geschatte wachttijd voor gesprekken die
 * op een menselijke medewerker wachten. Kantooruren-bewust (leunt op
 * OpeningHoursService).
 */
class ChatQueue
{
    public function __construct(private OpeningHoursService $hours)
    {
    }

    /**
     * 1-gebaseerde positie in de wachtrij van dezelfde site. Een gesprek dat al
     * aan een medewerker is toegewezen krijgt positie 0 ("een medewerker is bij je").
     * Alleen niet-toegewezen `waiting_human`-gesprekken tellen mee; ouder = vooraan.
     */
    public function positionOf(ChatConversation $conversation): int
    {
        if ($conversation->mode !== 'waiting_human') {
            return 0;
        }

        if ($conversation->assigned_agent_id) {
            return 0;
        }

        // Alleen gesprekken waar nog NIET op gereageerd is tellen mee: de bezoeker
        // was als laatste aan het woord. Een gesprek waar de agent al reageerde (of
        // dit gesprek zelf) staat niet meer in de rij, ook al blijft de mode
        // 'waiting_human' tot het wordt vrijgegeven/gesloten.
        if ($conversation->last_message_role !== 'visitor') {
            return 0;
        }

        $ahead = ChatConversation::query()
            ->where('site_id', $conversation->site_id)
            ->where('mode', 'waiting_human')
            ->whereNull('assigned_agent_id')
            ->where('last_message_role', 'visitor')
            ->where('id', '!=', $conversation->id)
            ->where(function ($q) use ($conversation): void {
                // Ouder (eerder aangemaakt) staat voor je; bij gelijke tijd op id.
                $q->where('created_at', '<', $conversation->created_at)
                    ->orWhere(function ($inner) use ($conversation): void {
                        $inner->where('created_at', $conversation->created_at)
                            ->where('id', '<', $conversation->id);
                    });
            })
            ->count();

        return $ahead + 1;
    }

    /**
     * Grove schatting: positie × gemiddelde afhandeltijd (config). Buiten
     * kantooruren geen zinnige schatting → null.
     */
    public function estimatedWaitMinutes(ChatConversation $conversation): ?int
    {
        if (! $this->hours->isOpen($conversation->site_id)) {
            return null;
        }

        $position = $this->positionOf($conversation);
        if ($position <= 0) {
            return 0;
        }

        $avgSeconds = (int) config('dashed-livechat.avg_handle_seconds', 180);

        return (int) ceil(($position * $avgSeconds) / 60);
    }

    public function isWithinOfficeHours(string $siteId): bool
    {
        return $this->hours->isOpen($siteId);
    }
}
