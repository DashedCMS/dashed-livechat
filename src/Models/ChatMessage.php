<?php

// src/Models/ChatMessage.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $table = 'dashed__chat_messages';
    protected $guarded = [];
    protected $casts = [
        'tool_calls' => 'array',
        'is_internal' => 'boolean',
        'feedback' => 'string',
        'attachments' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(ChatAgent::class, 'agent_id');
    }

    /**
     * Resolve stored media ids into a render-ready attachment list.
     *
     * @return array<int, array{id:int,url:string,thumb_url:string,mime:string,name:string,is_image:bool}>
     */
    public function attachmentsData(): array
    {
        $ids = $this->attachments ?? [];
        if (! is_array($ids) || empty($ids)) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $full = mediaHelper()->getSingleMedia($id, 'large');
            if (! is_object($full) || empty($full->url ?? null)) {
                continue;
            }
            $thumb = mediaHelper()->getSingleMedia($id, 'thumb');
            $mime = (string) ($full->mime ?? '');
            $isImage = str_starts_with($mime, 'image/') && $mime !== 'application/pdf';

            $out[] = [
                'id' => (int) $id,
                'url' => (string) $full->url,
                'thumb_url' => (is_object($thumb) && ! empty($thumb->url ?? null)) ? (string) $thumb->url : (string) $full->url,
                'mime' => $mime,
                'name' => basename((string) parse_url((string) $full->url, PHP_URL_PATH)),
                'is_image' => $isImage,
            ];
        }

        return $out;
    }
}
