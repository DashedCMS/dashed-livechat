<?php

namespace Dashed\DashedLivechat\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class VisitorGeo
{
    /**
     * Zoekt herkomst (land/stad/coordinaten) bij een IP via ip-api.com.
     * Resultaat wordt per IP een dag gecached; faalt stil (lege array).
     *
     * @return array{country: ?string, country_code: ?string, city: ?string, latitude: ?float, longitude: ?float}
     */
    public static function lookup(?string $ip): array
    {
        $empty = ['country' => null, 'country_code' => null, 'city' => null, 'latitude' => null, 'longitude' => null];

        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $empty;
        }

        return Cache::remember('livechat_geo:' . $ip, now()->addDay(), function () use ($ip, $empty) {
            return rescue(function () use ($ip, $empty) {
                $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,country,countryCode,city,lat,lon',
                ]);

                $data = $response->json();
                if (! is_array($data) || ($data['status'] ?? null) !== 'success') {
                    return $empty;
                }

                return [
                    'country' => $data['country'] ?? null,
                    'country_code' => $data['countryCode'] ?? null,
                    'city' => $data['city'] ?? null,
                    'latitude' => isset($data['lat']) ? (float) $data['lat'] : null,
                    'longitude' => isset($data['lon']) ? (float) $data['lon'] : null,
                ];
            }, $empty, false);
        });
    }
}
