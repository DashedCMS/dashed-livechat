<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TriggerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'placement' => $this->placement,
            'url_rules' => $this->url_rules ?? [],
            'exclude_urls' => $this->exclude_urls ?? [],
            'model_links' => $this->model_links ?? [],
            'trigger_type' => $this->trigger_type,
            'trigger_value' => $this->trigger_value !== null ? (int) $this->trigger_value : null,
            'proactive_message' => $this->proactive_message,
            'ai_agent_id' => $this->ai_agent_id,
        ];
    }
}
