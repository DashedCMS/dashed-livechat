<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'email' => $this->email,
            'user_id' => $this->user_id,
            'persona' => $this->persona,
            'tone' => $this->tone,
            'languages' => $this->languages ?? [],
            'allowed_topics' => $this->allowed_topics,
            'disallowed_topics' => $this->disallowed_topics,
            'escalation_rules' => $this->escalation_rules,
            'greeting' => $this->greeting,
            'model' => $this->model,
            'temperature' => $this->temperature !== null ? (float) $this->temperature : null,
            'guardrail_mode' => $this->guardrail_mode,
            'enabled_tools' => $this->enabled_tools ?? [],
            'abilities' => $this->abilities ?? [],
        ];
    }
}
