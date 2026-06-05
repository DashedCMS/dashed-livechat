<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_embeddings')) {
            // Een eerdere migratie kan de tabel zonder de unique index hebben
            // achtergelaten (te lange auto-naam op MySQL). Voeg 'm idempotent toe.
            try {
                Schema::table('dashed__chat_embeddings', function (Blueprint $table) {
                    $table->unique(['site_id', 'embeddable_type', 'embeddable_id'], 'chat_emb_unique');
                });
            } catch (\Throwable $e) {
                // Index bestaat al; niets te doen.
            }

            return;
        }

        Schema::create('dashed__chat_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('embeddable_type');
            $table->unsignedBigInteger('embeddable_id');
            $table->string('content_hash');
            $table->longText('vector');   // JSON-array van floats
            $table->timestamps();
            $table->unique(['site_id', 'embeddable_type', 'embeddable_id'], 'chat_emb_unique');
            $table->index(['site_id', 'embeddable_type'], 'chat_emb_site_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_embeddings');
    }
};
