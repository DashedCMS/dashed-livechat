<?php

// src/Models/ChatKnowledgeSource.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatKnowledgeSource extends Model
{
    protected $table = 'dashed__chat_knowledge_sources';
    protected $guarded = [];
    protected $casts = ['is_enabled' => 'boolean', 'config' => 'array'];
}
