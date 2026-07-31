<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatTagResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Filament\Resources\ChatTagResource;

class ListChatTags extends ListRecords
{
    protected static string $resource = ChatTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
