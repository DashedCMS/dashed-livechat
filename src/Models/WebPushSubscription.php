<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class WebPushSubscription extends Model
{
    protected $table = 'dashed__web_push_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];
}
