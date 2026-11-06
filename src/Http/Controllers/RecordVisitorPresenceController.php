<?php

namespace Dashed\DashedLivechat\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Classes\Sites;
use Illuminate\Support\Facades\Cache;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Support\VisitorGeo;
use Dashed\DashedLivechat\Models\VisitorSession;

class RecordVisitorPresenceController
{
    public function __invoke(Request $request)
    {
        $token = (string) $request->input('token', '');
        if ($token === '' || mb_strlen($token) > 64) {
            return response()->json(['ok' => false]);
        }

        $siteId = Sites::getActive();
        $ipHash = hash('sha256', $request->ip() . config('app.key'));

        $existing = VisitorSession::where('site_id', $siteId)->where('token', $token)->first();

        // Geo alleen (her)bepalen bij een nieuwe bezoeker of gewijzigd IP; scheelt lookups.
        $geo = [
            'country' => $existing?->country,
            'country_code' => $existing?->country_code,
            'city' => $existing?->city,
            'latitude' => $existing?->latitude,
            'longitude' => $existing?->longitude,
        ];
        if (! $existing || $existing->ip_hash !== $ipHash || $existing->country === null) {
            $geo = VisitorGeo::lookup($request->ip());
        }

        // Subtotaal = waarde van de producten in het mandje (zonder verzending/
        // korting), zodat "actieve mandjes" alleen mandjes met producten telt.
        $cartTotal = rescue(
            fn () => class_exists(\Dashed\DashedEcommerceCore\Classes\ShoppingCart::class)
                ? $this->cartSubtotal($request)
                : null,
            null,
            false
        );

        $values = [
            'last_seen_at' => now(),
            'url' => mb_substr((string) $request->input('url', ''), 0, 255) ?: null,
            'referrer' => mb_substr((string) $request->input('referrer', ''), 0, 255) ?: null,
            'ip_hash' => $ipHash,
            'country' => $geo['country'],
            'country_code' => $geo['country_code'],
            'city' => $geo['city'],
            'latitude' => $geo['latitude'],
            'longitude' => $geo['longitude'],
            'cart_total' => $cartTotal,
        ];

        // Bestaande rij is hierboven al gelezen. Alleen een nieuwe gaat via
        // updateOrCreate, die een gelijktijdige eerste hartslag opvangt.
        if ($existing) {
            $existing->update($values);
            $session = $existing;
        } else {
            $session = VisitorSession::updateOrCreate(['site_id' => $siteId, 'token' => $token], $values);
        }

        return response()->json([
            'ok' => true,
            'nudge' => $this->cartNudge($siteId, $token, (float) $cartTotal, $session),
        ]);
    }

    /**
     * Som over de winkelwagenregels, zelfde volgorde als CartHelper: eerst de
     * wagen van het cookie, zonder regels die van de ingelogde klant.
     * cartHelper()->getSubtotal() kan hier niet: zonder initialize() is dat
     * altijd 0, en initialize() rekent alles door en maakt per hartslag een
     * wagen aan. De web-groep op deze route ontsleutelt de cookie.
     */
    protected function cartSubtotal(Request $request): float
    {
        // Eén wagen, net als CartHelper: er kunnen er meer op dezelfde gebruiker staan.
        $sum = function (string $column, mixed $value): float {
            $cartId = DB::table('dashed__carts')->where('type', 'default')->where($column, $value)->orderBy('id')->value('id');

            return $cartId
                ? (float) DB::table('dashed__cart_items')->where('cart_id', $cartId)->sum(DB::raw('unit_price * quantity'))
                : 0.0;
        };

        $token = (string) $request->cookie(config('dashed-ecommerce.cart_cookie', 'cart_token'));
        $total = Str::isUuid($token) ? $sum('token', $token) : 0.0;

        if ($total <= 0 && auth()->id()) {
            $total = $sum('user_id', auth()->id());
        }

        return round($total, 2);
    }

    /**
     * Proactieve nudge voor een bezoeker met producten in het mandje die al
     * even op de site is. Maximaal eenmaal per uur per bezoeker (cache).
     */
    protected function cartNudge(string $siteId, string $token, float $cartTotal, VisitorSession $session): ?string
    {
        if ($cartTotal <= 0 || ! Customsetting::get('chat_cart_nudge', $siteId, false)) {
            return null;
        }

        // Niet meteen bij binnenkomst; pas als iemand al even rondkijkt.
        if (! $session->created_at || $session->created_at->gt(now()->subSeconds(90))) {
            return null;
        }

        $cacheKey = 'livechat_cart_nudged:' . $siteId . ':' . $token;
        if (Cache::has($cacheKey)) {
            return null;
        }

        Cache::put($cacheKey, true, now()->addHour());

        return Customsetting::get('chat_cart_nudge_message', $siteId)
            ?: 'Kan ik je ergens mee helpen met je bestelling? 🛒';
    }
}
