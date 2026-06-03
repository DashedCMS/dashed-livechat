<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_events')) {
            return;
        }
        Schema::create('dashed__chat_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_conversation_id')->index();
            $table->string('type');
            $table->unsignedBigInteger('agent_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_events');
    }
};
