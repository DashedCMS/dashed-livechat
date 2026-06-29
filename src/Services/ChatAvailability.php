<?php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Enums\AgentType;
use Dashed\DashedCore\Models\Customsetting;

/**
 * Bepaalt of de (mensen-bemande) chat nu bemand is en of er buiten
 * openingstijden een "reactie kan langer duren"-melding getoond moet worden.
 *
 * "Aan staan" = een actieve human-agent (`is_active`). Buiten openingstijden
 * telt alleen een agent die dat expliciet mag ontvangen (`receive_outside_hours`)
 * én moet de site-instelling daar ook op staan.
 */
class ChatAvailability
{
    public function __construct(private OpeningHoursService $hours)
    {
    }

    public function hasActiveHumanAgents(string $siteId): bool
    {
        return ChatAgent::where('site_id', $siteId)
            ->where('type', AgentType::Human->value)
            ->where('is_active', true)
            ->exists();
    }

    public function hasOutsideHoursAgents(string $siteId): bool
    {
        return ChatAgent::where('site_id', $siteId)
            ->where('type', AgentType::Human->value)
            ->where('is_active', true)
            ->where('receive_outside_hours', true)
            ->exists();
    }

    /** Site-instelling: buiten openingstijden chats blijven ontvangen. */
    public function acceptsOutsideHours(string $siteId): bool
    {
        return Customsetting::get('chat_out_of_hours_behavior', $siteId, 'ai_only') === 'accept_delayed';
    }

    /**
     * Is de chat nu bemand? Binnen openingstijden zodra er een actieve
     * human-agent is; buiten openingstijden alleen als dat is ingesteld én er
     * een agent is die buiten werktijden mag ontvangen.
     */
    public function isStaffed(string $siteId): bool
    {
        if ($this->hours->isOpen($siteId)) {
            return $this->hasActiveHumanAgents($siteId);
        }

        return $this->acceptsOutsideHours($siteId) && $this->hasOutsideHoursAgents($siteId);
    }

    /**
     * Toon de "reactie kan langer duren"-melding: de chat is bemand, maar we
     * zitten buiten openingstijden.
     */
    public function shouldShowDelayNotice(string $siteId): bool
    {
        return ! $this->hours->isOpen($siteId) && $this->isStaffed($siteId);
    }
}
