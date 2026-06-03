<?php

// src/Models/ChatEvent.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatEvent extends Model
{
    protected $table = 'dashed__chat_events';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(fn ($e) => $e->created_at ??= now());
    }
}
