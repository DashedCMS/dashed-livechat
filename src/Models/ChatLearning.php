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

    protected static function booted(): void
    {
        static::saved(function (ChatLearning $learning): void {
            rescue(function () use ($learning) {
                if (! $learning->is_active) {
                    return;
                }
                $driver = \Dashed\DashedCore\Models\Customsetting::get('chat_search_driver', $learning->site_id, 'embedding');
                if ($driver !== 'embedding') {
                    return;
                }
                $text = trim(($learning->question ?? '') . ' ' . ($learning->answer ?? ''));
                if ($text !== '') {
                    app(\Dashed\DashedLivechat\Ai\Knowledge\EmbeddingService::class)->upsertFor($learning, $text, $learning->site_id);
                }
            }, null, false);
        });
    }
}
