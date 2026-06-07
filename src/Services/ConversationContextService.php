<?php

declare(strict_types=1);

namespace Dashed\DashedLivechat\Services;

use Illuminate\Support\Facades\DB;
use Dashed\DashedLivechat\Models\ChatConversation;

/**
 * Detecteert gerelateerde klanten en bestellingen uit de gesprekstekst — spiegelt
 * de "gerelateerd"-sidebar van Filament (ViewChatConversation).
 */
class ConversationContextService
{
    /**
     * @return array{customers: array<int, array<string, mixed>>, orders: array<int, array<string, mixed>>}
     */
    public function relatedFor(ChatConversation $c): array
    {
        $text = (string) $c->messages()->pluck('content')->implode(' ');
        $emails = $this->detectEmails($c->visitor_email, $text);
        $refs = $this->detectOrderRefs($text);

        return [
            'customers' => $this->customers($emails),
            'orders' => $this->orders((string) $c->site_id, $emails, $refs),
        ];
    }

    /** @return array<int, string> */
    private function detectEmails(?string $visitorEmail, string $text): array
    {
        $emails = [];
        if ($visitorEmail) {
            $emails[] = $visitorEmail;
        }
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m);
        $emails = array_merge($emails, $m[0] ?? []);

        return array_values(array_unique(array_filter(
            array_map(static fn ($e): string => mb_strtolower(trim((string) $e)), $emails),
        )));
    }

    /** @return array<int, string> */
    private function detectOrderRefs(string $text): array
    {
        preg_match_all('/#?\b([A-Z0-9][A-Z0-9\-]{3,19})\b/i', $text, $m);

        return collect($m[1] ?? [])
            ->map(static fn ($t): string => trim((string) $t, " \t\n\r#"))
            ->filter(static fn ($t): bool => preg_match('/\d/', $t) === 1 && ! filter_var($t, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->take(30)
            ->values()
            ->all();
    }

    /** @param array<int, string> $emails @return array<int, array<string, mixed>> */
    private function customers(array $emails): array
    {
        $userClass = \Dashed\DashedCore\Models\User::class;
        if ($emails === [] || ! class_exists($userClass)) {
            return [];
        }

        return $userClass::query()
            ->whereIn(DB::raw('LOWER(email)'), $emails)
            ->limit(5)
            ->get()
            ->map(static fn ($u): array => [
                'id' => $u->id,
                'name' => trim((string) (($u->first_name ?? '') . ' ' . ($u->last_name ?? ''))) ?: ($u->name ?? $u->email),
                'email' => $u->email,
            ])
            ->all();
    }

    /** @param array<int, string> $emails @param array<int, string> $refs @return array<int, array<string, mixed>> */
    private function orders(string $siteId, array $emails, array $refs): array
    {
        $orderClass = \Dashed\DashedEcommerceCore\Models\Order::class;
        if (! class_exists($orderClass) || ($emails === [] && $refs === [])) {
            return [];
        }

        return $orderClass::query()
            ->where('site_id', $siteId)
            ->where('status', '!=', $orderClass::STATUS_CONCEPT)
            ->where(function ($query) use ($emails, $refs): void {
                if ($emails) {
                    $query->orWhereIn(DB::raw('LOWER(email)'), $emails);
                }
                if ($refs) {
                    $query->orWhereIn('invoice_id', $refs)->orWhereIn('hash', $refs);
                }
            })
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(static fn ($o): array => [
                'id' => $o->id,
                'invoice_id' => $o->invoice_id,
                'status' => $o->status,
                'fulfillment_status' => $o->fulfillment_status,
                'total' => $o->total !== null ? (float) $o->total : null,
                'created_at' => optional($o->created_at)->toIso8601String(),
            ])
            ->all();
    }
}
