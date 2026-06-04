<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatLearningResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Filament\Resources\ChatLearningResource;

class ListChatLearnings extends ListRecords
{
    protected static string $resource = ChatLearningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
