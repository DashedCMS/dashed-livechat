<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Support;

use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Vervangt variabelen (`{naam}`, `{shop}`, `{agent}`, `{email}`) in een
 * snippet-tekst door hun waarde voor het gegeven gesprek/medewerker/site.
 *
 * - Onbekende tokens blijven letterlijk staan (geen crash, geen lege
 *   vervanging) — zo blijft een typefout in een snippet zichtbaar i.p.v.
 *   stilzwijgend te verdwijnen.
 * - Tokennamen zijn case-insensitief (`{Naam}` == `{naam}`).
 * - Ontbrekende waardes krijgen een nette fallback: `{naam}` -> "daar",
 *   `{email}`/`{agent}` -> lege string (nooit een crash op een null-waarde).
 */
class SnippetRenderer
{
    public static function render(
        string $content,
        ?ChatConversation $conversation,
        ?User $agent,
        string $siteName
    ): string {
        $tokens = [
            'naam' => $conversation?->visitor_name ?: 'daar',
            'shop' => $siteName,
            'agent' => $agent?->name ?: '',
            'email' => $conversation?->visitor_email ?: '',
        ];

        return preg_replace_callback('/\{([a-zA-Z]+)\}/', function (array $matches) use ($tokens): string {
            $key = mb_strtolower($matches[1]);

            return array_key_exists($key, $tokens) ? (string) $tokens[$key] : $matches[0];
        }, $content);
    }
}
