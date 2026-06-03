<?php

// src/Models/ChatTrigger.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatTrigger extends Model
{
    protected $table = 'dashed__chat_triggers';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'url_rules' => 'array', 'exclude_urls' => 'array'];
}
