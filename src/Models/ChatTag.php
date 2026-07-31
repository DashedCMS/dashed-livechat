<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChatTag extends Model
{
    protected $table = 'dashed__chat_tags';

    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            ChatConversation::class,
            'dashed__chat_conversation_tag',
            'chat_tag_id',
            'chat_conversation_id',
        );
    }
}
