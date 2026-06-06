<?php

namespace Dashed\DashedLivechat\Http\Controllers;

use Illuminate\Http\Request;
use Dashed\DashedCore\Classes\Sites;
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

        $cartTotal = rescue(
            fn () => class_exists(\Dashed\DashedEcommerceCore\Classes\ShoppingCart::class)
                ? (float) cartHelper()->getTotal()
                : null,
            null,
            false
        );

        VisitorSession::updateOrCreate(
            ['site_id' => $siteId, 'token' => $token],
            [
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
            ],
        );

        return response()->json(['ok' => true]);
    }
}
