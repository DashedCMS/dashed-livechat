<?php

namespace Dashed\DashedLivechat\Services;

use Illuminate\Support\Collection;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Enums\AgentType;
use Dashed\DashedLivechat\Jobs\SendWebPushJob;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Models\WebPushPreference;
use Dashed\DashedLivechat\Models\WebPushSubscription;

class WebPushService
{
    public function __construct(private OpeningHoursService $hours)
    {
    }

    public function configured(): bool
    {
        return (bool) config('dashed-livechat.web_push.public_key')
            && (bool) config('dashed-livechat.web_push.private_key');
    }

    /**
     * Verstuur een bureaubladmelding naar de medewerkers die nu "aan staan"
     * en dit type ingeschakeld hebben. Sandbox-gesprekken en niet-geconfigureerde
     * installaties sturen niets.
     */
    public function notify(ChatConversation $c, string $type, string $title, string $body): void
    {
        if ($c->is_sandbox || ! $this->configured()) {
            return;
        }

        try {
            $payload = [
                'title' => $title,
                'body' => $body,
                'tag' => 'chat-' . $c->id,
                'url' => $this->conversationUrl($c),
                'conversationId' => $c->id,
            ];

            foreach ($this->recipientSubscriptions((string) $c->site_id, $type) as $subscription) {
                SendWebPushJob::dispatch($subscription->id, $payload);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Subscriptions van de aan-staande human-agents van de site die dit type
     * ingeschakeld hebben.
     *
     * @return Collection<int, WebPushSubscription>
     */
    public function recipientSubscriptions(string $siteId, string $type): Collection
    {
        $userIds = $this->onDutyAgentUserIds($siteId)
            ->filter(fn (int $userId) => WebPushPreference::enabledFor($userId, $siteId, $type))
            ->values();

        if ($userIds->isEmpty()) {
            return collect();
        }

        return WebPushSubscription::query()
            ->where('site_id', $siteId)
            ->whereIn('user_id', $userIds->all())
            ->get();
    }

    /**
     * User-id's van de actieve human-agents die nu berichten mogen ontvangen.
     * Buiten openingstijden alleen agents met receive_outside_hours (zelfde
     * regel als de bestaande mail-notificatie).
     *
     * @return Collection<int, int>
     */
    protected function onDutyAgentUserIds(string $siteId): Collection
    {
        $query = ChatAgent::query()
            ->where('site_id', $siteId)
            ->where('type', AgentType::Human->value)
            ->where('is_active', true)
            ->whereNotNull('user_id');

        if (! $this->hours->isOpen($siteId)) {
            $query->where('receive_outside_hours', true);
        }

        return $query->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    protected function conversationUrl(ChatConversation $c): string
    {
        $routeName = config('dashed-livechat.web_push.conversation_route');

        try {
            return route($routeName, ['record' => $c->id]);
        } catch (\Throwable $e) {
            return url('/');
        }
    }
}
