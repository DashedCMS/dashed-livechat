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

    protected static function booted(): void
    {
        // Het bedrijfsschema (dashed-core) cachet de openingstijden.
        $forget = function (): void {
            if (class_exists(\Dashed\DashedCore\Classes\Seo\BusinessProfile::class)) {
                \Dashed\DashedCore\Classes\Seo\BusinessProfile::forget();
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    protected $casts = [
        'day_of_week' => 'integer',   // 0=zo..6=za (Carbon dayOfWeek)
        'date' => 'date',
        'is_closed' => 'boolean',
    ];
}
