<?php

declare(strict_types=1);

use Livewire\Livewire;
use Dashed\DashedLivechat\Models\ChatAgent;
use Dashed\DashedLivechat\Livewire\Frontend\ChatWidget;

/**
 * `newAttachments` is via wire:model door de bezoeker te zetten; Livewire hydrateert
 * daar normaal TemporaryUploadedFile-instanties in. Bots sturen echter gemuteerde
 * payloads (bijv. `[1]`), waarna de widget-view crashte op
 * "Call to a member function getMimeType() on int". Alles wat geen echte upload
 * is, valt er daarom bij het zetten al uit.
 */
it('negeert waarden in newAttachments die geen upload zijn', function () {
    ChatAgent::create(['site_id' => 'main', 'type' => 'ai', 'name' => 'Testbot', 'is_active' => true]);

    Livewire::test(ChatWidget::class, ['siteId' => 'main'])
        ->set('newAttachments', [1, 'tekst', null])
        ->assertSet('newAttachments', [])
        ->assertOk();
});
