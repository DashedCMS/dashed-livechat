<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'dashed__app_notifications';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'delivered_at' => 'datetime',
    ];
}
