<?php

// src/Services/ChatAnalyticsService.php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedLivechat\Models\ChatEvent;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;

class ChatAnalyticsService
{
    public function forSite(string $siteId): array
    {
        // Sandbox-gesprekken komen uit de agent-testomgeving (ChatAgentPlayground)
        // en zijn geen echte bezoekers-traffic; ze mogen de statistieken niet
        // opblazen, dus die sluiten we hier expliciet uit.
        $conversationIds = ChatConversation::where('site_id', $siteId)
            ->where('is_sandbox', false)
            ->pluck('id');

        $byMode = ChatConversation::where('site_id', $siteId)
            ->where('is_sandbox', false)
            ->selectRaw('mode, count(*) as aantal')
            ->groupBy('mode')
            ->pluck('aantal', 'mode')
            ->toArray();

        $tokensIn = (int) ChatMessage::whereIn('chat_conversation_id', $conversationIds)->sum('tokens_in');
        $tokensOut = (int) ChatMessage::whereIn('chat_conversation_id', $conversationIds)->sum('tokens_out');

        $escalations = ChatEvent::whereIn('chat_conversation_id', $conversationIds)
            ->where('type', 'handoff_requested')
            ->count();

        // Feedback op AI-antwoorden (👍/👎) — de leer-loop-kwaliteit.
        $feedback = ChatMessage::whereIn('chat_conversation_id', $conversationIds)
            ->where('role', 'ai')
            ->whereIn('feedback', ['good', 'bad'])
            ->selectRaw('feedback, count(*) as aantal')
            ->groupBy('feedback')
            ->pluck('aantal', 'feedback')
            ->toArray();
        $feedbackGood = (int) ($feedback['good'] ?? 0);
        $feedbackBad = (int) ($feedback['bad'] ?? 0);

        // % zelf-afgehandeld: gesprekken die de AI afhandelde zonder escalatie
        // naar een mens (nooit handoff aangevraagd).
        $conversationCount = $conversationIds->count();
        $selfHandledPct = $conversationCount > 0
            ? (int) round(max(0, $conversationCount - $escalations) / $conversationCount * 100)
            : 0;

        $costUsd = $tokensIn / 1_000_000 * (float) config('dashed-livechat.cost_per_million_input', 3.0)
            + $tokensOut / 1_000_000 * (float) config('dashed-livechat.cost_per_million_output', 15.0);

        $costEur = $costUsd * (float) config('dashed-livechat.usd_to_eur', 0.92);

        // CSAT (uit de rating-kolommen). Sandbox is al uitgesloten.
        $rated = ChatConversation::where('site_id', $siteId)
            ->where('is_sandbox', false)
            ->whereNotNull('rating');
        $ratingCount = (clone $rated)->count();
        $ratingAvg = $ratingCount > 0 ? round((float) (clone $rated)->avg('rating'), 1) : null;
        $ratingPositive = (clone $rated)->where('rating', '>=', 4)->count();
        $csatPositivePct = $ratingCount > 0 ? (int) round($ratingPositive / $ratingCount * 100) : 0;

        // Tag-verdeling (top 8). withCount respecteert de ExcludeSandboxScope.
        $tagBreakdown = \Dashed\DashedLivechat\Models\ChatTag::where('site_id', $siteId)
            ->withCount('conversations')
            ->orderByDesc('conversations_count')
            ->limit(8)
            ->get()
            ->filter(fn ($t) => $t->conversations_count > 0)
            ->map(fn ($t) => ['name' => $t->name, 'color' => $t->color, 'count' => $t->conversations_count])
            ->values()
            ->all();

        // Drukte per uur (0-23), portable in PHP gebucket (geen DB-specifieke functies).
        $busyHours = array_fill(0, 24, 0);
        ChatConversation::where('site_id', $siteId)
            ->where('is_sandbox', false)
            ->orderByDesc('id')
            ->limit(2000)
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$busyHours): void {
                if ($createdAt) {
                    $busyHours[(int) $createdAt->format('G')]++;
                }
            });

        // Gemiddelde eerste-reactietijd (min), begrensd tot de recentste gesprekken.
        $recentIds = ChatConversation::where('site_id', $siteId)
            ->where('is_sandbox', false)
            ->orderByDesc('id')
            ->limit(500)
            ->pluck('id')
            ->all();
        $avgResponseMinutes = $this->averageFirstResponseMinutes($recentIds);

        return [
            'conversations' => $conversationCount,
            'by_mode' => array_merge(['ai' => 0, 'waiting_human' => 0, 'human' => 0], $byMode),
            'escalations' => $escalations,
            'self_handled_pct' => $selfHandledPct,
            'feedback_good' => $feedbackGood,
            'feedback_bad' => $feedbackBad,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'estimated_cost' => round($costUsd, 4),
            'estimated_cost_eur' => round($costEur, 4),
            'rating_count' => $ratingCount,
            'rating_avg' => $ratingAvg,
            'csat_positive_pct' => $csatPositivePct,
            'tag_breakdown' => $tagBreakdown,
            'busy_hours' => $busyHours,
            'avg_response_minutes' => $avgResponseMinutes,
        ];
    }

    /**
     * Gemiddelde tijd (in minuten) tussen het eerste bezoekersbericht en het
     * eerstvolgende AI/mens-antwoord, over de opgegeven gesprekken.
     *
     * @param  array<int, int>  $conversationIds
     */
    private function averageFirstResponseMinutes(array $conversationIds): ?int
    {
        if (empty($conversationIds)) {
            return null;
        }

        $messages = ChatMessage::whereIn('chat_conversation_id', $conversationIds)
            ->where('is_internal', false)
            ->whereIn('role', ['visitor', 'ai', 'human'])
            ->orderBy('chat_conversation_id')
            ->orderBy('id')
            ->get(['chat_conversation_id', 'role', 'created_at']);

        $firstVisitorAt = [];
        $deltas = [];

        foreach ($messages as $message) {
            $cid = $message->chat_conversation_id;

            if ($message->role === 'visitor') {
                // Onthoud alleen het eerste bezoekersbericht per gesprek.
                if (! isset($firstVisitorAt[$cid])) {
                    $firstVisitorAt[$cid] = $message->created_at;
                }

                continue;
            }

            // Eerste AI/mens-antwoord ná een bezoekersbericht: reactietijd.
            if (isset($firstVisitorAt[$cid]) && $firstVisitorAt[$cid] !== null && $message->created_at) {
                $deltas[$cid] ??= abs($message->created_at->diffInSeconds($firstVisitorAt[$cid]));
            }
        }

        if (empty($deltas)) {
            return null;
        }

        return (int) round((array_sum($deltas) / count($deltas)) / 60);
    }
}
