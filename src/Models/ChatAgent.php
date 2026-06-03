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
        'languages' => 'array',
        'enabled_tools' => 'array',
        'temperature' => 'float',
    ];
}
