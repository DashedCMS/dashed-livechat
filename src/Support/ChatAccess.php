<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Support;

use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\ChatAgent;

/**
 * Bepaalt de effectieve livechat-rechten van een user voor één site.
 *
 * Regels (afgesproken met de klant):
 *  - alleen een superadmin (de vlag is_superadmin) heeft altijd volledige toegang;
 *  - iedere andere user moet expliciet als actieve *human* ChatAgent aan die
 *    site gekoppeld zijn ("livechat-medewerker"); zo niet → geen toegang;
 *  - de rechten van een medewerker staan op de ChatAgent (`abilities`), maar
 *    nooit ruimer dan zijn CMS-rollen: lezen, antwoorden en overnemen vragen
 *    view_chat of manage_chat, chat.manage vraagt manage_chat. Zo mag iemand in
 *    de app nooit meer dan in het CMS; de ChatAgent-rij is een extra voorwaarde.
 */
class ChatAccess
{
    public const ABILITIES = ['chat.read', 'chat.reply', 'chat.takeover', 'chat.manage'];

    /** Standaardrechten voor een medewerker zonder expliciet ingestelde set. */
    public const DEFAULT_ABILITIES = ['chat.read', 'chat.reply', 'chat.takeover'];

    /** De CMS-rechten achter elke chat-ability; één ervan volstaat. */
    public const PERMISSIONS = [
        'chat.read' => ['view_chat', 'manage_chat'],
        'chat.reply' => ['view_chat', 'manage_chat'],
        'chat.takeover' => ['view_chat', 'manage_chat'],
        'chat.manage' => ['manage_chat'],
    ];

    /**
     * @return array<int, string>
     */
    public static function abilitiesForUser(User $user, string $siteId): array
    {
        if ($user->isSuperadmin()) {
            return self::ABILITIES;
        }

        $agent = self::agentFor($user, $siteId);
        if (! $agent) {
            return [];
        }

        $abilities = is_array($agent->abilities) ? $agent->abilities : self::DEFAULT_ABILITIES;

        return array_values(array_filter(
            array_intersect($abilities, self::ABILITIES),
            fn (string $ability): bool => self::rolesAllow($user, $ability),
        ));
    }

    private static function rolesAllow(User $user, string $ability): bool
    {
        foreach (self::PERMISSIONS[$ability] ?? [] as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public static function isAgent(User $user, string $siteId): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return self::agentFor($user, $siteId) !== null && self::rolesAllow($user, 'chat.read');
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
