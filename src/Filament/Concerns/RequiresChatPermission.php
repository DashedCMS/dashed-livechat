<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Filament\Concerns;

/**
 * Toegang tot de livechat in het CMS komt uit de rollen: view_chat voor de
 * gesprekken zelf (lezen, beantwoorden, overnemen) en manage_chat voor de
 * inrichting (agents, kennis, triggers, openingstijden, snelle antwoorden).
 * Wie mag inrichten mag ook de gesprekken zien. Een superadmin mag via
 * Gate::before altijd alles.
 *
 * De app volgt dit niet: daar beslist ChatAccess per site op de ChatAgent-rij.
 */
trait RequiresChatPermission
{
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (static::requiredChatPermission() === 'view_chat' && $user->can('manage_chat')) {
            return true;
        }

        return $user->can(static::requiredChatPermission());
    }

    /** 'view_chat' of 'manage_chat'; standaard de inrichting. */
    protected static function requiredChatPermission(): string
    {
        return 'manage_chat';
    }
}
