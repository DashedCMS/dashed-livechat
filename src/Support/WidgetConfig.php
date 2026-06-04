<?php

namespace Dashed\DashedLivechat\Support;

use Dashed\DashedCore\Models\Customsetting;

class WidgetConfig
{
    public static function for(string $siteId): array
    {
        return [
            'enabled' => (bool) Customsetting::get('chat_enabled', $siteId, false),
            'primary' => Customsetting::get('chat_primary_color', $siteId, '#111827'),
            'on_primary' => Customsetting::get('chat_on_primary_color', $siteId, '#ffffff'),
            'radius' => (int) Customsetting::get('chat_radius', $siteId, 16),
            'position' => Customsetting::get('chat_position', $siteId, 'right'),
            'offset' => (int) Customsetting::get('chat_offset', $siteId, 24),
            'title' => Customsetting::get('chat_title', $siteId, 'Chat met ons'),
            'greeting' => Customsetting::get('chat_greeting', $siteId, 'Hoi! Waar kan ik je mee helpen?'),
            'avatar' => self::resolveImage(Customsetting::get('chat_avatar_url', $siteId)),
            'phone' => Customsetting::get('chat_contact_phone', $siteId) ?: Customsetting::get('company_phone_number', $siteId),
            'email' => Customsetting::get('chat_contact_email', $siteId) ?: Customsetting::get('site_from_email', $siteId),
        ];
    }

    protected static function resolveImage($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Already a URL/path (back-compat with old FileUpload values)
        if (is_string($value) && (str_contains($value, '/') || str_contains($value, '.'))) {
            return $value;
        }

        return rescue(fn () => mediaHelper()->getSingleMedia($value, 'medium')?->url, null, false);
    }
}
