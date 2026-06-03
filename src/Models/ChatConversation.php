<?php

// src/Models/ChatConversation.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatConversation extends Model
{
    protected $table = 'dashed__chat_conversations';
    protected $guarded = [];
    protected $casts = ['meta' => 'array', 'last_message_at' => 'datetime'];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChatEvent::class);
    }

    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class, 'ai_agent_id');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class, 'assigned_agent_id');
    }
}
