<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatTagResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatTagResource;

class EditChatTag extends EditRecord
{
    protected static string $resource = ChatTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
