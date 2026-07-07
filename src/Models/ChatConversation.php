<?php

// src/Models/ChatConversation.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Dashed\DashedLivechat\Models\Scopes\ExcludeSandboxScope;

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
        static::addGlobalScope(new ExcludeSandboxScope());

        static::deleting(function (self $conversation): void {
            $conversation->messages()->delete();
            $conversation->events()->delete();
        });
    }

    /**
     * Aanwezigheid van de bezoeker op de website, afgeleid van
     * visitor_last_active_at (de widget werkt dat ~elke 10s bij zolang de
     * bezoeker een pagina open heeft): 'active' = nu actief op de site,
     * 'idle' = net nog actief (waarschijnlijk nog op de site, tab op de
     * achtergrond), 'away' = al een tijd geen teken van leven / van de site af.
     */
    /**
     * De site-brede presence-sessie van de bezoeker (gekoppeld via het
     * beacon-token dat de widget opslaat). last_seen_at loopt óók op de
     * achtergrond door (beacon elke ~25-60s) en stopt zodra de bezoeker weg is.
     */
    public function visitorSession(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VisitorSession::class, 'token', 'visitor_session_token');
    }

    public function visitorPresence(): string
    {
        $activeWithin = (int) config('dashed-livechat.presence_active_seconds', 45);
        $awayAfter = (int) config('dashed-livechat.presence_away_seconds', 120);

        // 'active' = nu bezig in de widget/op de voorgrond (widget-poll bumpt
        // visitor_last_active_at; die bevriest zodra de tab op de achtergrond gaat).
        $lastActive = $this->visitor_last_active_at;
        if ($lastActive && $lastActive->gt(now()->subSeconds($activeWithin))) {
            return 'active';
        }

        // Nog op de site? De presence-beacon blijft ook op de achtergrond pingen.
        $lastSeen = optional($this->visitorSession)->last_seen_at;
        $mostRecent = collect([$lastActive, $lastSeen])->filter()->max();
        if ($mostRecent && $mostRecent->gt(now()->subSeconds($awayAfter))) {
            return 'idle';
        }

        return 'away';
    }

    /**
     * Haalt de sandbox-uitsluiting weg zodat sandbox-conversaties (de
     * agent-testomgeving) ook meekomen in de resultaten. Gebruik dit alleen
     * op plekken die bewust met sandbox-data werken (bv. de playground zelf).
     */
    public function scopeWithSandbox($query)
    {
        return $query->withoutGlobalScope(ExcludeSandboxScope::class);
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
