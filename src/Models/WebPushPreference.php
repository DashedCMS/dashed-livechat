<?php

namespace Dashed\DashedLivechat\Models;

use Illuminate\Database\Eloquent\Model;

class WebPushPreference extends Model
{
    protected $table = 'dashed__web_push_preferences';

    protected $guarded = [];

    protected $casts = [
        'notify_handoff' => 'boolean',
        'notify_message' => 'boolean',
        'notify_new' => 'boolean',
    ];

    /**
     * Standaardwaarden per type wanneer er nog geen rij bestaat.
     *
     * @var array<string, bool>
     */
    public const DEFAULTS = [
        'handoff' => true,
        'message' => true,
        'new' => false,
    ];

    public static function enabledFor(int $userId, string $siteId, string $type): bool
    {
        if (! array_key_exists($type, self::DEFAULTS)) {
            return false;
        }

        $preference = static::query()
            ->where('user_id', $userId)
            ->where('site_id', $siteId)
            ->first();

        if (! $preference) {
            return self::DEFAULTS[$type];
        }

        return (bool) $preference->{'notify_' . $type};
    }
}
