<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatConversationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Filament\Resources\ChatConversationResource;

class ListChatConversations extends ListRecords
{
    protected static string $resource = ChatConversationResource::class;
}
