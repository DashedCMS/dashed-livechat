<?php

namespace Dashed\DashedLivechat\Jobs;

use Illuminate\Bus\Queueable;
use Minishlink\WebPush\WebPush;
use Illuminate\Queue\SerializesModels;
use Minishlink\WebPush\Subscription;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Dashed\DashedLivechat\Models\WebPushSubscription;

class SendWebPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public int $subscriptionId, public array $payload)
    {
    }

    public function handle(): void
    {
        $subscription = WebPushSubscription::find($this->subscriptionId);
        if (! $subscription) {
            return;
        }

        $publicKey = config('dashed-livechat.web_push.public_key');
        $privateKey = config('dashed-livechat.web_push.private_key');
        if (! $publicKey || ! $privateKey) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('dashed-livechat.web_push.subject'),
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);

            $report = $webPush->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
                ]),
                json_encode($this->payload),
            );

            if ($report->isSuccess()) {
                $subscription->forceFill(['last_used_at' => now()])->save();

                return;
            }

            // 404/410: het endpoint bestaat niet meer, subscription opruimen.
            if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                $subscription->delete();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
