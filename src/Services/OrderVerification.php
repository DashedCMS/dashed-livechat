<?php

namespace Dashed\DashedLivechat\Services;

use Dashed\DashedLivechat\Models\ChatEvent;
use Dashed\DashedEcommerceCore\Models\Order;
use Dashed\DashedLivechat\Models\ChatConversation;

class OrderVerification
{
    public function verify(ChatConversation $conversation, ?string $orderNumber, ?string $email): OrderVerificationResult
    {
        $max = (int) config('dashed-livechat.order_lookup_max_attempts', 5);

        if ($conversation->order_lookup_attempts >= $max) {
            $this->logEvent($conversation, 'order_verify_blocked', $orderNumber, $email);

            return new OrderVerificationResult('blocked');
        }

        $orderNumber = trim((string) $orderNumber);
        $email = trim((string) $email);

        // Lege invoer telt niet als poging; vraag gewoon opnieuw.
        if ($orderNumber === '' || $email === '') {
            return new OrderVerificationResult('not_found');
        }

        // Elke volwaardige poging telt mee (anti brute-force), ook een succesvolle.
        $conversation->increment('order_lookup_attempts');

        if (! class_exists(Order::class)) {
            return new OrderVerificationResult('not_found');
        }

        $order = Order::query()
            ->where('site_id', $conversation->site_id)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
            ->where('status', '!=', Order::STATUS_CONCEPT)
            ->where(function ($q) use ($orderNumber) {
                $q->where(function ($q2) use ($orderNumber) {
                    $q2->where('invoice_id', $orderNumber)
                        ->whereNotIn('invoice_id', ['PROFORMA', 'RETURN']);
                })->orWhere('hash', $orderNumber);
            })
            ->latest('id')
            ->first();

        if (! $order) {
            $this->logEvent($conversation, 'order_verify_failed', $orderNumber, $email);

            return new OrderVerificationResult('not_found');
        }

        return new OrderVerificationResult('found', $order);
    }

    public static function mask(?string $value): string
    {
        $value = (string) $value;
        if (mb_strlen($value) < 8) {
            return '****';
        }

        $tail = mb_substr($value, -4);

        return str_repeat('*', mb_strlen($value) - 4) . $tail;
    }

    protected function logEvent(ChatConversation $conversation, string $type, ?string $orderNumber, ?string $email): void
    {
        ChatEvent::create([
            'chat_conversation_id' => $conversation->id,
            'type' => $type,
            'payload' => [
                'order_number' => self::mask($orderNumber),
                'email' => self::mask($email),
            ],
        ]);
    }
}
