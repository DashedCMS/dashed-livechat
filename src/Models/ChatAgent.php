<?php

// src/Models/ChatAgent.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatAgent extends Model
{
    protected $table = 'dashed__chat_agents';
    protected $guarded = [];
    protected $casts = [
        'is_active' => 'boolean',
        'receive_outside_hours' => 'boolean',
        'languages' => 'array',
        'enabled_tools' => 'array',
        'abilities' => 'array',
        'temperature' => 'float',
        'ai_reply_delay_seconds' => 'integer',
        'max_tokens' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (ChatAgent $agent): void {
            if ($agent->type !== 'human' || ! $agent->user_id) {
                return;
            }

            $user = \Dashed\DashedCore\Models\User::find($agent->user_id);
            if (! $user) {
                return;
            }

            if (empty($agent->name)) {
                $agent->name = $user->name ?: $user->email;
            }

            if (empty($agent->email)) {
                $agent->email = $user->email;
            }
        });
    }
}
