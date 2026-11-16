<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Dashed\DashedLivechat\Jobs\PrivatizeChatAttachments;
use Dashed\DashedCore\Security\Uploads\PrivateUploads;

/**
 * Oude chatbijlagen uit de publieke mediabibliotheek naar de privé opslag
 * (dashed-core PrivateUploads), via een queued job. Alleen in productie:
 * lokaal draait deze migratie vaak op een kopie van de productiedatabase met
 * de echte DigitalOcean-disk erachter.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_messages') || ! Schema::hasColumn('dashed__chat_messages', 'attachments') || ! PrivateUploads::privatizeOnMigrate()) {
            return;
        }

        rescue(fn () => PrivatizeChatAttachments::dispatch(), null, true);
    }

    public function down(): void
    {
    }
};
