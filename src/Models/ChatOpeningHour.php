<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatOpeningHour extends Model
{
    protected $table = 'dashed__chat_opening_hours';

    protected $guarded = [];

    protected $attributes = [
        'is_closed' => false,
    ];

    protected $casts = [
        'day_of_week' => 'integer',   // 0=zo..6=za (Carbon dayOfWeek)
        'date' => 'date',
        'is_closed' => 'boolean',
    ];
}
