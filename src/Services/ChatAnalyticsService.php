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
        ];
    }
}
