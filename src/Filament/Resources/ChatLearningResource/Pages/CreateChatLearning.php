<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatLearningResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatLearningResource;

class CreateChatLearning extends CreateRecord
{
    protected static string $resource = ChatLearningResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Sites::getActive();

        return $data;
    }
}
