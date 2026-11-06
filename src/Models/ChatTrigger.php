<?php

// src/Models/ChatTrigger.php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;

class ChatTrigger extends Model
{
    protected $table = 'dashed__chat_triggers';
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'url_rules' => 'array', 'exclude_urls' => 'array', 'model_links' => 'array'];

    private const ACTIVE_CACHE_KEY = 'dashed-livechat:active-triggers';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::ACTIVE_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::ACTIVE_CACHE_KEY));
    }

    /**
     * De actieve triggers, zwaarste eerst. Wordt op elke frontendpagina gelezen.
     * Gecachet als kale rijen en niet als modellen, zodat een andere klasse na
     * een uitrol geen kapotte cache oplevert.
     */
    public static function activeCached(): Collection
    {
        return static::hydrate(Cache::remember(
            self::ACTIVE_CACHE_KEY,
            now()->addHour(),
            fn () => static::where('is_active', true)->orderByDesc('sort_order')->get()->map->getAttributes()->all()
        ));
    }
}
