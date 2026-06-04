<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatOpeningHourResource;

class CreateChatOpeningHour extends CreateRecord
{
    protected static string $resource = ChatOpeningHourResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Sites::getActive();

        return $data;
    }
}
