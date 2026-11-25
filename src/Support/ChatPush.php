<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Support;

use Throwable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Dashed\DashedCore\Models\User;
use Dashed\DashedLivechat\Models\ChatAgent;

/**
 * App-pushes van de livechat (nieuwe chat, nieuw bericht, live bezoekers).
 *
 * Die gingen via het recht chat.read van de app, en dat recht volgt de
 * CMS-rollen. Maar in de app beslist ChatAccess wie bij de chat mag: buiten
 * de superadmin alleen een actieve medewerker (ChatAgent) van die site. Een
 * push naar iemand die de chat daarna niet kan openen is ruis, en een gesprek
 * van een andere site hoort niet op zijn telefoon. Daarom gaat de push hier
 * naar precies de gebruikers die ChatAccess chat.read geeft voor de site, met
 * dezelfde voorkeur-, stille-uren- en sessiefilters als de app zelf toepast.
 *
 * De app-module is optioneel: zonder dashed-mobile-api doet dit niets.
 */
class ChatPush
{
    public const NOTIFICATION_CENTER = '\Dashed\DashedMobileApi\Support\NotificationCenter';

    private const DEVICE_TOKEN = '\Dashed\DashedMobileApi\Models\DeviceToken';

    private const PREFERENCES = '\Dashed\DashedMobileApi\Support\NotificationPreferences';

    private const QUIET_HOURS = '\Dashed\DashedMobileApi\Support\QuietHours';

    private const INBOX = '\Dashed\DashedMobileApi\Models\MobileNotification';

    /**
     * Wie de chat van deze site mag lezen: de superadmins en de gebruikers
     * van de actieve menselijke medewerkers, gefilterd door ChatAccess zelf
     * (een medewerker met een eigen rechtenset zonder chat.read valt af).
     *
     * @return Collection<int, User>
     */
    public static function recipients(string $siteId): Collection
    {
        $agentUserIds = ChatAgent::query()
            ->where('site_id', $siteId)
            ->where('type', 'human')
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        /** @var class-string<User> $userModel */
        $userModel = config('auth.providers.users.model') ?: User::class;

        return $userModel::query()
            ->where(fn ($query) => $query->superadmins()->orWhereIn('id', $agentUserIds))
            ->get()
            ->filter(fn (User $user): bool => ChatAccess::can($user, $siteId, 'chat.read'))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function send(
        string $siteId,
        string $type,
        string $title,
        string $body,
        ?string $route = null,
        array $data = [],
        ?string $category = null,
    ): void {
        if (! class_exists(self::NOTIFICATION_CENTER) || ! class_exists(self::DEVICE_TOKEN)) {
            return;
        }

        // Nieuwere mobile-api kan zelf naar gekozen gebruikers pushen, met
        // voorkeuren, stille uren, sessiefilter en inbox. Het recht uit
        // type() is bij toUsers() geen ontvangerfilter, dus de ontvangers
        // blijven precies wie ChatAccess toelaat.
        $push = app(self::NOTIFICATION_CENTER)->push();
        if (method_exists($push, 'toUsers')) {
            $recipients = self::recipients($siteId);
            if ($recipients->isEmpty()) {
                return;
            }

            $push->type($type)
                ->site($siteId)
                ->sound('chat')
                ->title($title)
                ->body($body)
                ->route($route)
                ->data($data)
                ->category($category)
                ->toUsers($recipients)
                ->send();

            return;
        }

        // Terugval voor een oudere mobile-api zonder toUsers().
        $users = self::recipients($siteId)->filter(fn (User $user): bool => self::wants($user, $type, $siteId));
        if ($users->isEmpty()) {
            return;
        }

        self::persist($users, $siteId, $type, $title, $body, $route, $data);

        $tokens = self::tokensFor($users->reject(fn (User $user): bool => self::isQuiet($user)));
        if ($tokens === []) {
            return;
        }

        // Bewust zonder ->type(): dat zet het app-recht als ontvangerfilter,
        // en dan gaat de push weer naar iedereen met chat.read in plaats van
        // naar deze ontvangers.
        app(self::NOTIFICATION_CENTER)->push()
            ->site($siteId)
            ->sound('chat')
            ->title($title)
            ->body($body)
            ->route($route)
            ->data($data)
            ->category($category)
            ->toTokens($tokens)
            ->send();
    }

    private static function wants(User $user, string $type, string $siteId): bool
    {
        if (! class_exists(self::PREFERENCES)) {
            return true;
        }

        return (bool) rescue(fn () => app(self::PREFERENCES)->wants($user, $type, $siteId), true, false);
    }

    private static function isQuiet(User $user): bool
    {
        if (! class_exists(self::QUIET_HOURS)) {
            return false;
        }

        return (bool) rescue(fn () => app(self::QUIET_HOURS)->isQuiet($user), false, false);
    }

    /**
     * De Expo-tokens van deze gebruikers, alleen van apparaten waarvan de
     * app-sessie nog bestaat (een uitgelogd toestel krijgt niets).
     *
     * @param  Collection<int, User>  $users
     * @return array<int, string>
     */
    private static function tokensFor(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $deviceTokens = (self::DEVICE_TOKEN)::query()->whereIn('user_id', $users->pluck('id')->all())->get();

        $sessionIds = $deviceTokens->pluck('access_token_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $live = $sessionIds === []
            ? []
            : array_flip(DB::table('personal_access_tokens')->whereIn('id', $sessionIds)->pluck('id')->map(fn ($id): int => (int) $id)->all());

        return $deviceTokens
            ->filter(fn ($device): bool => $device->access_token_id === null || isset($live[(int) $device->access_token_id]))
            ->pluck('token')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Een rij in de inbox van de app per ontvanger, zoals de app dat ook doet
     * voor een push op recht. Best effort: dit mag de push nooit tegenhouden.
     *
     * @param  Collection<int, User>  $users
     * @param  array<string, mixed>  $data
     */
    private static function persist(Collection $users, string $siteId, string $type, string $title, string $body, ?string $route, array $data): void
    {
        if (! class_exists(self::INBOX)) {
            return;
        }

        foreach ($users as $user) {
            try {
                (self::INBOX)::create([
                    'user_id' => $user->id,
                    'site_id' => $siteId,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body !== '' ? $body : null,
                    'data' => ($route !== null ? $data + ['route' => $route] : $data) ?: null,
                    'route' => $route,
                ]);
            } catch (Throwable) {
                // Inbox is best effort.
            }
        }
    }
}
