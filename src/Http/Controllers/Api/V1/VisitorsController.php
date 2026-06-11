<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedLivechat\Models\VisitorSession;

class VisitorsController extends Controller
{
    public function live(): JsonResponse
    {
        $visitors = VisitorSession::query()
            ->where('site_id', (string) Sites::getActive())
            ->live(120)
            ->get();

        $topPages = $visitors
            ->groupBy(fn ($v): string => parse_url((string) $v->url, PHP_URL_PATH) ?: '/')
            ->map(fn ($group, $path): array => ['path' => $path, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values()
            ->all();

        $countries = $visitors
            ->groupBy(fn ($v): string => $v->country ?: 'Onbekend')
            ->map(fn ($group, $country): array => ['name' => $country, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(12)
            ->values()
            ->all();

        return response()->json([
            'live_count' => $visitors->count(),
            'cart_total' => (float) $visitors->sum('cart_total'),
            'active_carts' => $visitors->filter(fn ($v): bool => (float) $v->cart_total > 0)->count(),
            'top_pages' => $topPages,
            'countries' => $countries,
            'points' => $visitors
                ->filter(fn ($v): bool => $v->latitude !== null && $v->longitude !== null)
                ->map(fn ($v): array => [
                    'lat' => (float) $v->latitude,
                    'lng' => (float) $v->longitude,
                    'country' => $v->country ?: null,
                    'city' => $v->city ?? null,
                ])
                ->values()
                ->take(500)
                ->all(),
        ]);
    }
}
