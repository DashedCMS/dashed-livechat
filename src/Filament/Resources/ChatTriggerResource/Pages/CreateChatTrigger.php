<?php

namespace Dashed\DashedLivechat\Filament\Resources\ChatTriggerResource\Pages;

use Dashed\DashedCore\Classes\Sites;
use Filament\Resources\Pages\CreateRecord;
use Dashed\DashedLivechat\Filament\Resources\ChatTriggerResource;

class CreateChatTrigger extends CreateRecord
{
    protected static string $resource = ChatTriggerResource::class;

    /**
     * Site speelt geen rol bij triggers (alles draait op dezelfde site), maar
     * de kolom is verplicht. We vullen 'm automatisch met de actieve site zodat
     * de beheerder geen site hoeft in te vullen.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['site_id'] = $data['site_id'] ?? (string) Sites::getActive();

        return $data;
    }
}
