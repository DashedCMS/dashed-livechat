<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_knowledge_sources')) {
            return;
        }
        Schema::create('dashed__chat_knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('type');                 // products|pages|articles|faq
            $table->boolean('is_enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_knowledge_sources');
    }
};
