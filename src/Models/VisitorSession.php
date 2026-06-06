<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class VisitorSession extends Model
{
    protected $table = 'dashed__chat_visitor_sessions';

    protected $guarded = [];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'cart_total' => 'float',
    ];

    /** Bezoekers die in de laatste $seconds seconden actief waren. */
    public function scopeLive(Builder $query, int $seconds = 60): Builder
    {
        return $query->where('last_seen_at', '>=', now()->subSeconds($seconds));
    }
}
