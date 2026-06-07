<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Dashed\DashedCore\Classes\Sites;
use Dashed\DashedCore\Models\Customsetting;

class ChatSettingsController extends Controller
{
    /**
     * Mapping camelCase API-veld => [customsetting-key, type, default].
     *
     * @var array<string, array{0:string,1:string,2:mixed}>
     */
    private const FIELDS = [
        'enabled' => ['chat_enabled', 'bool', false],
        'title' => ['chat_title', 'string', 'Chat met ons'],
        'greeting' => ['chat_greeting', 'string', 'Hoi! Waar kan ik je mee helpen?'],
        'primaryColor' => ['chat_primary_color', 'string', '#111827'],
        'onPrimaryColor' => ['chat_on_primary_color', 'string', '#ffffff'],
        'radius' => ['chat_radius', 'int', 16],
        'position' => ['chat_position', 'string', 'right'],
        'offset' => ['chat_offset', 'int', 24],
        'outOfHoursBehavior' => ['chat_out_of_hours_behavior', 'string', 'ai_only'],
        'searchDriver' => ['chat_search_driver', 'string', 'fulltext'],
        'contactPhone' => ['chat_contact_phone', 'string', null],
        'contactEmail' => ['chat_contact_email', 'string', null],
        'handoffNotifications' => ['chat_handoff_notifications', 'bool', true],
        'newMessageIndicator' => ['chat_new_message_indicator', 'string', 'badge'],
        'visitorNotifications' => ['chat_visitor_notifications', 'bool', false],
        'visitorNotificationsMin' => ['chat_visitor_notifications_min', 'int', 1],
    ];

    public function show(): JsonResponse
    {
        $siteId = (string) Sites::getActive();
        $out = [];

        foreach (self::FIELDS as $apiKey => [$key, $type, $default]) {
            $value = Customsetting::get($key, $siteId, null);
            $out[$apiKey] = $value === null ? $default : $this->cast($value, $type);
        }

        return response()->json(['data' => $out]);
    }

    public function update(Request $request): JsonResponse
    {
        $siteId = (string) Sites::getActive();

        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:255'],
            'greeting' => ['sometimes', 'string'],
            'primaryColor' => ['sometimes', 'string', 'max:32'],
            'onPrimaryColor' => ['sometimes', 'string', 'max:32'],
            'radius' => ['sometimes', 'integer', 'min:0', 'max:48'],
            'position' => ['sometimes', Rule::in(['right', 'left'])],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:200'],
            'outOfHoursBehavior' => ['sometimes', Rule::in(['ai_only', 'contact_form', 'callback'])],
            'searchDriver' => ['sometimes', Rule::in(['fulltext', 'embedding'])],
            'contactPhone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'contactEmail' => ['sometimes', 'nullable', 'email'],
            'handoffNotifications' => ['sometimes', 'boolean'],
            'newMessageIndicator' => ['sometimes', Rule::in(['badge', 'preview'])],
            'visitorNotifications' => ['sometimes', 'boolean'],
            'visitorNotificationsMin' => ['sometimes', 'integer', 'min:1'],
        ]);

        foreach ($data as $apiKey => $value) {
            [$key, $type] = self::FIELDS[$apiKey];
            $stored = match ($type) {
                'bool' => $value ? '1' : '0',
                'int' => (string) (int) $value,
                default => $value === null ? null : (string) $value,
            };
            Customsetting::set($key, $stored, $siteId);
        }

        return $this->show();
    }

    private function cast(mixed $value, string $type): mixed
    {
        return match ($type) {
            'bool' => (bool) $value && $value !== '0',
            'int' => (int) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
