<?php

namespace Dashed\DashedLivechat\Filament\Pages;

use UnitEnum;
use BackedEnum;
use Filament\Pages\Page;
use Dashed\DashedLivechat\Models\VisitorSession;

class VisitorsLivePage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Chat';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-europe-africa';

    protected static ?string $navigationLabel = 'Live bezoekers';

    protected static ?string $title = 'Live bezoekers';

    protected static ?int $navigationSort = 3;

    protected string $view = 'dashed-livechat::filament.visitors-live';

    public int $liveCount = 0;

    public float $cartTotal = 0.0;

    public bool $showCart = false;

    public array $countries = [];

    public array $points = [];

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
