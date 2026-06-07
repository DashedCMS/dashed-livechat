<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'agent' => $this->agent ? [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'type' => $this->agent->type,
            ] : null,
            'tool_calls' => $this->tool_calls,
            'feedback' => $this->feedback,
        ];
    }
}
