<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mode' => $this->mode,
            'status' => $this->status,
            'visitor_name' => $this->visitor_name,
            'visitor_email' => $this->visitor_email,
            'locale' => $this->locale,
            'assigned_agent_id' => $this->assigned_agent_id,
            'last_message_at' => optional($this->last_message_at)->toIso8601String(),
            // 'agent' = jij moet antwoorden, 'visitor' = wachten op de bezoeker.
            'awaiting' => $this->awaiting,
            // Aanwezigheid bezoeker: 'active' | 'idle' | 'away'.
            'visitor_presence' => $this->visitorPresence(),
            'visitor_last_active_at' => optional($this->visitor_last_active_at)->toIso8601String(),
            'visitor' => (function () {
                $r = $this->returningVisitorInfo();

                return [
                    'ip' => $this->visitor_ip,
                    'user_agent' => $this->visitor_user_agent,
                    'started_url' => $this->started_url,
                    'referrer' => $this->visitor_referrer,
                    'city' => $this->visitor_city,
                    'country' => $this->visitor_country,
                    'is_returning' => $r['is_returning'],
                    'previous_count' => $r['count'],
                    'last_seen_before' => optional($r['last_at'])->toIso8601String(),
                ];
            })(),
        ];
    }
}
