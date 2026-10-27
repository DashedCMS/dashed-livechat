<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Dashed\DashedLivechat\Models\ChatConversation;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource;

class ListChatConversations extends ListRecords
{
    protected static string $resource = ChatConversationResource::class;

    public function getTabs(): array
    {
        $open = fn (Builder $query): Builder => $query->where('status', '!=', 'closed');
        $yourTurn = fn (Builder $query): Builder => $open($query)->awaitingAgent();

        return [
            'your_turn' => Tab::make(__('Jij bent aan zet'))
                ->icon('heroicon-m-chat-bubble-left-ellipsis')
                ->badge(fn () => $yourTurn(ChatConversation::query())->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing($yourTurn),
            'open' => Tab::make(__('Open'))
                ->badge(fn () => $open(ChatConversation::query())->count() ?: null)
                ->modifyQueryUsing($open),
            'closed' => Tab::make(__('Afgerond'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'closed')),
            'all' => Tab::make(__('Alles')),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'open';
    }
}
