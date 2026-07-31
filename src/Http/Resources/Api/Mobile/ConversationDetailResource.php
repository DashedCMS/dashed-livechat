<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationDetailResource extends JsonResource
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
            'started_url' => $this->started_url,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'last_message_at' => optional($this->last_message_at)->toIso8601String(),
            'visitor_read_at' => optional($this->visitor_read_at)->toIso8601String(),
            'visitor_presence' => $this->visitorPresence(),
            'visitor_last_active_at' => optional($this->visitor_last_active_at)->toIso8601String(),
            'ai_agent' => $this->aiAgent ? ['id' => $this->aiAgent->id, 'name' => $this->aiAgent->name] : null,
            'assigned_agent' => $this->assignedAgent ? ['id' => $this->assignedAgent->id, 'name' => $this->assignedAgent->name] : null,
            'related' => $this->related_context ?? ['customers' => [], 'orders' => []],
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'color' => $t->color,
            ])->values(), []),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($n) => [
                'id' => $n->id,
                'body' => $n->body,
                'author' => $n->author_name,
                'created_at' => optional($n->created_at)->toIso8601String(),
            ])->values(), []),
            // Live winkelmandje/pagina van de bezoeker (uit de presence-sessie).
            // Null als er geen (verse) sessie meer is (opgeruimd na ~1 dag).
            'cart' => (function () {
                $s = $this->visitorSession;
                if (! $s || $s->cart_total === null) {
                    return null;
                }

                return [
                    'total' => (float) $s->cart_total,
                    'current_url' => $s->url,
                    'updated_at' => optional($s->last_seen_at)->toIso8601String(),
                ];
            })(),
            // Bewust ook 'started_url' hierin (dupliceert de top-level sleutel
            // hierboven) zodat het 'visitor'-blok overal dezelfde vaste vorm heeft.
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
