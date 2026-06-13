<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatQuickReply extends Model
{
    protected $table = 'dashed__chat_quick_replies';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
    ];
}
