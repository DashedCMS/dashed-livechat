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
