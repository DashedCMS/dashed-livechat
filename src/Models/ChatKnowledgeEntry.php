<?php

// src/Models/ChatKnowledgeEntry.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatKnowledgeEntry extends Model
{
    protected $table = 'dashed__chat_knowledge_entries';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
