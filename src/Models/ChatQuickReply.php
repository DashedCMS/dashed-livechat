<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ChatQuickReply extends Model
{
    protected $table = 'dashed__chat_quick_replies';

    // `$guarded = []` maakt alle kolommen (incl. `shortcut`/`owner_id`) al
    // mass-assignable; er is bewust geen los `$fillable` in dit model.
    protected $guarded = [];

    protected $casts = [
        'sort' => 'integer',
        'owner_id' => 'integer',
    ];

    /**
     * Snippets die zichtbaar zijn voor `$userId` op site `$siteId`: gedeelde
     * snippets (`owner_id` is null) plus de eigen persoonlijke snippets.
     * Snippets van een andere user blijven verborgen.
     */
    public function scopeVisibleTo(Builder $query, ?int $userId, string $siteId): Builder
    {
        return $query
            ->where('site_id', $siteId)
            ->where(function (Builder $w) use ($userId) {
                $w->whereNull('owner_id');
                if ($userId !== null) {
                    $w->orWhere('owner_id', $userId);
                }
            });
    }
}
