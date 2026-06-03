<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class ChatEmbedding extends Model
{
    protected $table = 'dashed__chat_embeddings';

    protected $guarded = [];

    protected $casts = [
        'vector' => 'array',
    ];
}
