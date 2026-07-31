<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatTagResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatTagResource;

class CreateChatTag extends CreateRecord
{
    protected static string $resource = ChatTagResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Sites::getActive();

        return $data;
    }
}
