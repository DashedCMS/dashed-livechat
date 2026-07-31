<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_conversation_tag')) {
            return;
        }

        Schema::create('dashed__chat_conversation_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('chat_conversation_id')->index();
            $table->unsignedBigInteger('chat_tag_id')->index();

            $table->unique(['chat_conversation_id', 'chat_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_conversation_tag');
    }
};
