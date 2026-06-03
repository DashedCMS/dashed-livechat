<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_conversations')) {
            return;
        }
        Schema::create('dashed__chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->uuid('public_token')->unique();
            $table->string('visitor_name')->nullable();
            $table->string('visitor_email')->nullable();
            $table->string('locale')->nullable();
            $table->string('status')->default('active');   // active|closed
            $table->string('mode')->default('ai');         // ai|waiting_human|human
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('started_url')->nullable();
            $table->integer('order_lookup_attempts')->default(0);
            $table->string('ip_hash')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_conversations');
    }
};
