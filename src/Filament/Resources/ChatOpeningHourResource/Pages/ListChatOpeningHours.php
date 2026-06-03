<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource;

class ListChatOpeningHours extends ListRecords
{
    protected static string $resource = ChatOpeningHourResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
