<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatLearning extends Model
{
    protected $table = 'dashed__chat_learnings';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
