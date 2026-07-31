<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_messages')) {
            return;
        }
        Schema::create('dashed__chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_conversation_id')->index();
            $table->string('role');                 // visitor|ai|human|system
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->text('content');
            // Vertaal-cache (zie 2026_07_31_130000_add_translation_to_chat_messages_table);
            // hier ook meteen opgenomen voor verse/testomgevingen (migratievolgorde).
            $table->text('translated_content')->nullable();
            $table->string('source_locale', 16)->nullable();
            $table->json('attachments')->nullable();
            $table->json('tool_calls')->nullable();
            $table->boolean('is_internal')->default(false);
            $table->string('feedback')->nullable();
            $table->text('feedback_note')->nullable();
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_messages');
    }
};
