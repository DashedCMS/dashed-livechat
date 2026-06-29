<?php

namespace Dashed\DashedLivechat\Commands;

use Throwable;
use Illuminate\Console\Command;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\VisitorSession;
use Dashed\DashedLivechat\Models\AppNotification;

class NotifyVisitorCountCommand extends Command
{
    protected $signature = 'dashed-livechat:notify-visitor-count';

    protected $description = 'Stuurt een push met het aantal live bezoekers naar app-gebruikers die "Live bezoekers" aan hebben (max 1 per 5 min per site).';

    public function handle(): int
    {
        $siteIds = VisitorSession::query()->distinct()->pluck('site_id');
        $pushed = 0;

        foreach ($siteIds as $siteId) {
            $min = max(1, (int) Customsetting::get('chat_visitor_notifications_min', $siteId, 1));
            $count = VisitorSession::where('site_id', $siteId)->live(120)->count();
            if ($count < $min) {
                continue;
            }

            // Throttle: max 1 melding per 5 min per site (AppNotification = marker).
            $recent = AppNotification::where('site_id', $siteId)
                ->where('channel', 'visitors')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();
            if ($recent) {
                continue;
            }

            $cartTotal = (float) VisitorSession::where('site_id', $siteId)->live(120)->sum('cart_total');
            $cartLabel = $cartTotal > 0 ? ' (mandjes: € ' . number_format($cartTotal, 2, ',', '.') . ')' : '';
            $title = $count . ' bezoeker' . ($count === 1 ? '' : 's') . ' online';
            $body = 'Er ' . ($count === 1 ? 'is' : 'zijn') . ' nu ' . $count . ' bezoeker'
                . ($count === 1 ? '' : 's') . ' op de website' . $cartLabel . '.';

            // In-app record (feed) + dient als throttle-marker.
            AppNotification::create([
                'site_id' => $siteId,
                'channel' => 'visitors',
                'title' => $title,
                'body' => $body,
                'data' => ['visitor_count' => $count, 'cart_total' => $cartTotal],
            ]);

            // Push naar app-gebruikers die het type 'visitors.live' aan hebben staan.
            // De per-gebruiker-voorkeur (default uit) bepaalt wie 'm krijgt.
            $center = '\Dashed\DashedMobileApi\Support\NotificationCenter';
            if (class_exists($center)) {
                try {
                    app($center)->push()
                        ->type('visitors.live')
                        ->site((string) $siteId)
                        ->title($title)
                        ->body($body)
                        ->route('/visitors')
                        ->data(['type' => 'visitors', 'count' => $count])
                        ->send();
                    $pushed++;
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        $this->info("Bezoekers-pushes verstuurd: {$pushed}.");

        return self::SUCCESS;
    }
}
