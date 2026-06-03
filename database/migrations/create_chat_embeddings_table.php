<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_embeddings')) {
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
            $table->unique(['site_id', 'embeddable_type', 'embeddable_id']);
            $table->index(['site_id', 'embeddable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_embeddings');
    }
};
