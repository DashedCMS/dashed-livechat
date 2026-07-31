<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        // Expliciete korte index-naam: de auto-gegenereerde naam
        // (dashed__chat_conversation_tag_chat_conversation_id_chat_tag_id_unique)
        // overschrijdt de MySQL-limiet van 64 tekens.
        if (! Schema::hasTable('dashed__chat_conversation_tag')) {
            Schema::create('dashed__chat_conversation_tag', function (Blueprint $table) {
                $table->unsignedBigInteger('chat_conversation_id')->index();
                $table->unsignedBigInteger('chat_tag_id')->index();

                $table->unique(['chat_conversation_id', 'chat_tag_id'], 'chat_conv_tag_unique');
            });

            return;
        }

        // Herstel: tabel bestaat al (bv. door een eerder half-gefaalde migratie op
        // MySQL) maar mist mogelijk de unique-index → best-effort toevoegen.
        rescue(function (): void {
            Schema::table('dashed__chat_conversation_tag', function (Blueprint $table) {
                $table->unique(['chat_conversation_id', 'chat_tag_id'], 'chat_conv_tag_unique');
            });
        }, report: false);
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_conversation_tag');
    }
};
