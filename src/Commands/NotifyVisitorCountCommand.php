<?php

namespace Dashed\DashedLivechat\Commands;

use Illuminate\Console\Command;
use Dashed\DashedCore\Models\Customsetting;
use Dashed\DashedLivechat\Models\AppNotification;
use Dashed\DashedLivechat\Models\VisitorSession;

class NotifyVisitorCountCommand extends Command
{
    protected $signature = 'dashed-livechat:notify-visitor-count';

    protected $description = 'Zet een app-notificatie klaar met het aantal live bezoekers (max 1 per 5 min per site).';

    public function handle(): int
    {
        $siteIds = VisitorSession::query()->distinct()->pluck('site_id');
        $queued = 0;

        foreach ($siteIds as $siteId) {
            if (! Customsetting::get('chat_visitor_notifications', $siteId, false)) {
                continue;
            }

            $min = max(1, (int) Customsetting::get('chat_visitor_notifications_min', $siteId, 1));
            $count = VisitorSession::where('site_id', $siteId)->live(120)->count();
            if ($count < $min) {
                continue;
            }

            // Max 1 per 5 minuten per site.
            $recent = AppNotification::where('site_id', $siteId)
                ->where('channel', 'visitors')
                ->where('created_at', '>=', now()->subMinutes(5))
                ->exists();
            if ($recent) {
                continue;
            }

            $cartTotal = (float) VisitorSession::where('site_id', $siteId)->live(120)->sum('cart_total');
            $cartLabel = $cartTotal > 0 ? ' (mandjes: € ' . number_format($cartTotal, 2, ',', '.') . ')' : '';

            AppNotification::create([
                'site_id' => $siteId,
                'channel' => 'visitors',
                'title' => $count . ' bezoeker' . ($count === 1 ? '' : 's') . ' online',
                'body' => 'Er ' . ($count === 1 ? 'is' : 'zijn') . ' nu ' . $count . ' bezoeker'
                    . ($count === 1 ? '' : 's') . ' op de website' . $cartLabel . '.',
                'data' => ['visitor_count' => $count, 'cart_total' => $cartTotal],
            ]);

            $queued++;
        }

        $this->info("Notificaties klaargezet: {$queued}.");

        return self::SUCCESS;
    }
}
