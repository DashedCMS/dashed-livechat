<?php

// src/Models/ChatMessage.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'dashed__chat_messages';
    protected $guarded = [];
    protected $casts = [
        'tool_calls' => 'array',
        'is_internal' => 'boolean',
        'feedback' => 'string',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class, 'agent_id');
    }
}
