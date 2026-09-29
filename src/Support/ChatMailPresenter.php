<?php

namespace Dashed\DashedLivechat\Support;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Dashed\DashedCore\Mail\MailBranding;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Models\ChatMessage;
use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Bouwt de viewdata voor de klantmails van de chat (transcript en
 * offline-antwoord), zodat die er precies zo uitzien als de widget: dezelfde
 * kleuren en afronding, dezelfde afzenderlabels, dezelfde avatars, en onze
 * berichten door dezelfde markdown-render.
 */
class ChatMailPresenter
{
    /**
     * @param  Collection<int, ChatMessage>  $messages
     * @return array<string, mixed>
     */
    public static function for(ChatConversation $conversation, Collection $messages): array
    {
        $siteId = $conversation->site_id;
        $cfg = WidgetConfig::for($siteId);
        $branding = MailBranding::for($siteId);
        $persona = self::persona($siteId);

        $personaAvatar = $persona?->avatar
            ? (rescue(fn () => mediaHelper()->getSingleMedia($persona->avatar, 'medium')?->url, null, false) ?: $cfg['avatar'])
            : $cfg['avatar'];

        $avatarByAgent = [];
        $rows = $messages->map(function (ChatMessage $message) use ($persona, $personaAvatar, &$avatarByAgent) {
            $isVisitor = $message->role === 'visitor';
            $label = self::senderLabel($message, $persona?->name);

            $avatar = null;
            if (! $isVisitor) {
                $agentId = $message->agent_id;
                if ($agentId && ! array_key_exists($agentId, $avatarByAgent)) {
                    $avatarByAgent[$agentId] = $message->agent?->avatar
                        ? rescue(fn () => mediaHelper()->getSingleMedia($message->agent->avatar, 'medium')?->url, null, false)
                        : null;
                }
                $avatar = ($agentId ? $avatarByAgent[$agentId] : null) ?: $personaAvatar;
            }

            return [
                'isVisitor' => $isVisitor,
                'label' => $label,
                'initial' => mb_strtoupper(mb_substr($label, 0, 1)),
                'time' => $message->created_at?->format('d-m-Y H:i'),
                'avatar' => $avatar,
                'html' => $isVisitor ? nl2br(e((string) $message->content)) : self::markdown($message),
                'attachments' => rescue(fn () => $message->attachmentsData(), [], false),
                'cards' => self::productCards($message),
            ];
        })->values()->all();

        return [
            'branding' => $branding,
            'siteName' => $branding['siteName'],
            'chat' => [
                'primary' => $cfg['primary'],
                'onPrimary' => $cfg['on_primary'],
                'radius' => $cfg['radius'],
                'phone' => $cfg['phone'],
                'email' => $cfg['email'],
            ],
            'rows' => $rows,
            'resumeUrl' => self::resumeUrl($conversation),
        ];
    }

    /**
     * De pagina waar het gesprek begon (of de site-URL) met de public_token als
     * query-parameter; de widget pikt die op en hervat het gesprek.
     */
    public static function resumeUrl(ChatConversation $conversation): string
    {
        $base = $conversation->started_url ?: config('app.url');
        $separator = str_contains((string) $base, '?') ? '&' : '?';

        return $base . $separator . 'dashed_chat=' . $conversation->public_token;
    }

    /** Zelfde regels als de widget: "Jij", de persona bij naam, de medewerker bij voornaam. */
    public static function senderLabel(ChatMessage $message, ?string $personaName): string
    {
        if ($message->role === 'visitor') {
            return 'Jij';
        }

        $agentName = $message->agent?->name;

        if ($message->role === 'human') {
            $first = $agentName ? (explode(' ', trim($agentName))[0] ?: $agentName) : 'Medewerker';

            return $first . ' · medewerker';
        }

        return $agentName ?: ($personaName ?: 'Assistent');
    }

    protected static function persona(string $siteId): ?ChatAgent
    {
        return ChatAgent::where('site_id', $siteId)
            ->where('type', 'ai')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Dezelfde markdown-render als de widget, met inline marges omdat een
     * mailclient geen stylesheet voor de chat-berichten kent.
     */
    protected static function markdown(ChatMessage $message): string
    {
        $html = Str::markdown((string) ($message->translated_content ?: $message->content), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return str_replace(
            ['<p>', '<ul>', '<ol>', '<li>'],
            ['<p style="margin:0 0 8px;">', '<ul style="margin:0 0 8px; padding-left:20px;">', '<ol style="margin:0 0 8px; padding-left:20px;">', '<li style="margin:0 0 2px;">'],
            trim($html)
        );
    }

    /** @return array<int, array{name: string, url: string, price: ?float, image: ?string}> */
    protected static function productCards(ChatMessage $message): array
    {
        return collect($message->tool_calls ?? [])
            ->pluck('products')
            ->filter()
            ->flatten(1)
            ->filter(fn ($p) => is_array($p) && ! empty($p['name']))
            ->unique('url')
            ->take(6)
            ->map(fn ($p) => [
                'name' => (string) $p['name'],
                'url' => (string) ($p['url'] ?? '#'),
                'price' => isset($p['price']) && $p['price'] !== '' ? (float) $p['price'] : null,
                'image' => ! empty($p['image']) ? (string) $p['image'] : null,
            ])
            ->values()
            ->all();
    }
}
