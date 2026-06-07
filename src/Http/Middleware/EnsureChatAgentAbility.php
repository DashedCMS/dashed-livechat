<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Support\ChatAccess;

/**
 * Beschermt livechat-routes: de user moet een livechat-medewerker zijn voor de
 * actieve site (of superadmin) én het gevraagde recht hebben. Vervangt de
 * generieke `ability:`-middleware voor chat, omdat chat-rechten per site en per
 * medewerker gelden i.p.v. via het (site-loze) token.
 */
class EnsureChatAgentAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $siteId = (string) ($request->attributes->get('mobile_site_id')
            ?: (Sites::getActive() ?: (Sites::getFirstSite()['id'] ?? '')));

        if (! ChatAccess::can($user, $siteId, $ability)) {
            abort(403, 'Geen livechat-toegang voor deze actie.');
        }

        return $next($request);
    }
}
