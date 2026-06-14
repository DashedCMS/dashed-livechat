<?php

namespace Dashed\DashedLivechat\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Dashed\DashedLivechat\Models\VisitorSession;
use Dashed\DashedLivechat\Filament\Concerns\HiddenWhenChatDisabled;

class VisitorsLivePage extends Page
{
    use HiddenWhenChatDisabled;

    protected static string|UnitEnum|null $navigationGroup = 'Chat';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationLabel = 'Live bezoekers';

    protected static ?string $title = 'Live bezoekers';

    protected static ?int $navigationSort = 3;

    protected string $view = 'dashed-livechat::filament.visitors-live';

    public int $liveCount = 0;

    public float $cartTotal = 0.0;

    public int $activeCarts = 0;

    public float $revenueToday = 0.0;

    public int $ordersToday = 0;

    public bool $showCart = false;

    public array $countries = [];

    public array $topPages = [];

    public array $points = [];

    public array $feed = [];

    public function mount(): void
    {
        $this->showCart = class_exists(\Dashed\DashedEcommerceCore\Classes\ShoppingCart::class);
        $this->refreshData();
    }

    public function pollData(): void
    {
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $live = VisitorSession::query()->live(120)->get();

        $this->liveCount = $live->count();
        $this->cartTotal = (float) $live->sum('cart_total');
        $this->activeCarts = $live->filter(fn ($v) => (float) $v->cart_total > 0)->count();

        if ($this->showCart && class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)) {
            $todayPaid = fn () => \Dashed\DashedEcommerceCore\Models\Order::query()
                ->whereDate('created_at', today())
                ->whereIn('status', ['paid', 'partially_paid', 'waiting_for_confirmation']);

            $this->revenueToday = (float) rescue(fn () => (clone $todayPaid())->sum('total'), 0.0, false);
            $this->ordersToday = (int) rescue(fn () => (clone $todayPaid())->count(), 0, false);
        }

        $this->topPages = $live->groupBy(fn ($v) => parse_url((string) $v->url, PHP_URL_PATH) ?: '/')
            ->map(fn ($group, $path) => ['path' => $path, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(8)
            ->values()
            ->all();

        $this->countries = $live->groupBy(fn ($v) => $v->country ?: 'Onbekend')
            ->map(fn ($group, $country) => ['name' => $country, 'count' => $group->count()])
            ->sortByDesc('count')
            ->take(12)
            ->values()
            ->all();

        $this->points = $live->filter(fn ($v) => $v->latitude && $v->longitude)
            ->map(fn ($v) => [
                'lat' => (float) $v->latitude,
                'lng' => (float) $v->longitude,
                'city' => $v->city,
                'country' => $v->country,
                'cart' => (float) $v->cart_total,
            ])
            ->values()
            ->all();

        $this->feed = $this->buildFeed($live);
    }

    /** Live activiteiten-feed: recente bezoekers + bestellingen van vandaag. */
    protected function buildFeed($live): array
    {
        $items = [];

        foreach ($live->sortByDesc('last_seen_at')->take(15) as $v) {
            $where = trim(implode(', ', array_filter([$v->city, $v->country])));
            $path = parse_url((string) $v->url, PHP_URL_PATH) ?: '/';
            $cart = (float) $v->cart_total > 0 ? ' · mandje € ' . number_format((float) $v->cart_total, 2, ',', '.') : '';
            $items[] = [
                'ts' => $v->last_seen_at?->getTimestamp() ?? 0,
                'time' => $v->last_seen_at?->format('H:i'),
                'type' => (float) $v->cart_total > 0 ? 'cart' : 'visitor',
                'text' => ($where ?: 'Bezoeker') . ' · ' . $path . $cart,
            ];
        }

        if ($this->showCart && class_exists(\Dashed\DashedEcommerceCore\Models\Order::class)) {
            $orders = rescue(fn () => \Dashed\DashedEcommerceCore\Models\Order::query()
                ->whereDate('created_at', today())
                ->whereIn('status', ['paid', 'partially_paid', 'waiting_for_confirmation'])
                ->latest('id')
                ->limit(10)
                ->get(['id', 'total', 'first_name', 'created_at']), collect(), false);

            foreach ($orders as $o) {
                $items[] = [
                    'ts' => $o->created_at?->getTimestamp() ?? 0,
                    'time' => $o->created_at?->format('H:i'),
                    'type' => 'order',
                    'text' => 'Bestelling € ' . number_format((float) $o->total, 2, ',', '.') . ($o->first_name ? ' · ' . $o->first_name : ''),
                ];
            }
        }

        usort($items, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return array_slice($items, 0, 15);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = VisitorSession::query()->live(120)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
