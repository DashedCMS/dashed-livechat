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
    protected $casts = [
        'meta' => 'array',
        'last_message_at' => 'datetime',
        'visitor_read_at' => 'datetime',
        'visitor_last_active_at' => 'datetime',
        'is_sandbox' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $conversation): void {
            $conversation->messages()->delete();
            $conversation->events()->delete();
        });
    }

    /**
     * Wie is aan de beurt: 'agent' wanneer de bezoeker als laatste iets stuurde
     * (jij moet antwoorden), anders 'visitor' (wachten op de bezoeker).
     */
    public function getAwaitingAttribute(): string
    {
        return $this->last_message_role === 'visitor' ? 'agent' : 'visitor';
    }

    /** Gesprekken die op een antwoord van de medewerker wachten. */
    public function scopeAwaitingAgent($query)
    {
        return $query->where('last_message_role', 'visitor');
    }

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

    public function markClosed(): void
    {
        $this->forceFill(['status' => 'closed'])->save();
    }

    public function reopen(): void
    {
        $this->forceFill(['status' => 'active'])->save();
    }
}
