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
            'ai_agent' => $this->aiAgent ? ['id' => $this->aiAgent->id, 'name' => $this->aiAgent->name] : null,
            'assigned_agent' => $this->assignedAgent ? ['id' => $this->assignedAgent->id, 'name' => $this->assignedAgent->name] : null,
            'related' => $this->related_context ?? ['customers' => [], 'orders' => []],
        ];
    }
}
