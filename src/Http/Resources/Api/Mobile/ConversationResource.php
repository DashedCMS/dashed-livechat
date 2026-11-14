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
            // Wie stuurde het laatste bericht ('visitor' | 'ai' | 'human'), zodat de
            // app de preview kan labelen.
            'last_message_role' => $this->last_message_role,
            // Korte preview van het laatste zichtbare bericht voor het overzicht.
            'last_message_preview' => $this->whenLoaded('latestMessage', function () {
                $last = $this->latestMessage;
                if (! $last) {
                    return null;
                }
                $text = trim((string) ($last->content ?? ''));
                if ($text === '' && is_array($last->attachments) && $last->attachments !== []) {
                    $text = '📎 Bijlage';
                }

                return $text === '' ? null : \Illuminate\Support\Str::limit($text, 80);
            }, null),
            // 'agent' = jij moet antwoorden, 'visitor' = wachten op de bezoeker.
            'awaiting' => $this->awaiting,
            // Aanwezigheid bezoeker: 'active' | 'idle' | 'away'.
            'visitor_presence' => $this->visitorPresence(),
            'visitor_last_active_at' => optional($this->visitor_last_active_at)->toIso8601String(),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($t) => [
                'id' => $t->id, 'name' => $t->name, 'color' => $t->color,
            ])->values(), []),
        ];
    }
}
