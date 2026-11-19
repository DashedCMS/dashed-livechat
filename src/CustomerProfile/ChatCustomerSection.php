<?php

namespace Dashed\DashedLivechat\CustomerProfile;

use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource;

/**
 * Chatgesprekken van een klant voor het klantprofiel in ec-core
 * (cms()->builder('customerProfileSections')), gevonden op het
 * e-mailadres dat de bezoeker in de chat gaf.
 */
class ChatCustomerSection
{
    public static function label(): string
    {
        return __('Chats');
    }

    public static function sort(): int
    {
        return 60;
    }

    public static function forCustomer(?int $userId, ?string $email): ?array
    {
        if (! $email) {
            return null;
        }

        $query = ChatConversation::query()->where('visitor_email', $email);
        $total = (clone $query)->count();

        if ($total === 0) {
            return null;
        }

        $conversations = $query->latest('id')->limit(10)->get();

        return [
            'stats' => [
                ['label' => __('Gesprekken'), 'value' => (string) $total],
            ],
            'columns' => [
                'date' => __('Datum'),
                'status' => __('Status'),
                'rating' => __('Beoordeling'),
            ],
            'rows' => $conversations->map(fn (ChatConversation $conversation) => [
                'date' => $conversation->created_at?->format('d-m-Y H:i'),
                'status' => $conversation->status === 'closed' ? __('Gesloten') : __('Actief'),
                'rating' => $conversation->rating ? $conversation->rating . '/5' : '-',
                '_url' => rescue(fn () => ChatConversationResource::getUrl('view', ['record' => $conversation->id]), null, false),
            ])->all(),
            'empty' => __('Geen chats.'),
        ];
    }
}
