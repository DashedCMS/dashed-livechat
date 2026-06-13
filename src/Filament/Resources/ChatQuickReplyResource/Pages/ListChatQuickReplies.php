<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatQuickReplyResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Filament\Resources\ChatQuickReplyResource;

class ListChatQuickReplies extends ListRecords
{
    protected static string $resource = ChatQuickReplyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
