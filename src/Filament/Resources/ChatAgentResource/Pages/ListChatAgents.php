<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatAgentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Filament\Resources\ChatAgentResource;

class ListChatAgents extends ListRecords
{
    protected static string $resource = ChatAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
