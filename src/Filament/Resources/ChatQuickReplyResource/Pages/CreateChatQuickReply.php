<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatQuickReplyResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatQuickReplyResource;

class CreateChatQuickReply extends CreateRecord
{
    protected static string $resource = ChatQuickReplyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = Sites::getActive();

        return $data;
    }
}
