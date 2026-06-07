<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Support;

use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\ChatAgent;

/**
 * Bepaalt de effectieve livechat-rechten van een user voor één site.
 *
 * Regels (afgesproken met de klant):
 *  - alleen een `superadmin` heeft altijd volledige toegang;
 *  - iedere andere user moet expliciet als actieve *human* ChatAgent aan die
 *    site gekoppeld zijn ("livechat-medewerker"); zo niet → geen toegang;
 *  - de rechten van een medewerker staan op de ChatAgent (`abilities`).
 */
class ChatAccess
{
    public const ABILITIES = ['chat.read', 'chat.reply', 'chat.takeover', 'chat.manage'];

    /** Standaardrechten voor een medewerker zonder expliciet ingestelde set. */
    public const DEFAULT_ABILITIES = ['chat.read', 'chat.reply', 'chat.takeover'];

    /**
     * @return array<int, string>
     */
    public static function abilitiesForUser(User $user, string $siteId): array
    {
        if (($user->role ?? null) === 'superadmin') {
            return self::ABILITIES;
        }

        $agent = self::agentFor($user, $siteId);
        if (! $agent) {
            return [];
        }

        $abilities = is_array($agent->abilities) ? $agent->abilities : self::DEFAULT_ABILITIES;

        return array_values(array_intersect($abilities, self::ABILITIES));
    }

    public static function isAgent(User $user, string $siteId): bool
    {
        if (($user->role ?? null) === 'superadmin') {
            return true;
        }

        return self::agentFor($user, $siteId) !== null;
    }

    public static function can(User $user, string $siteId, string $ability): bool
    {
        return in_array($ability, self::abilitiesForUser($user, $siteId), true);
    }

    private static function agentFor(User $user, string $siteId): ?ChatAgent
    {
        return ChatAgent::query()
            ->where('site_id', $siteId)
            ->where('type', 'human')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
    }
}
