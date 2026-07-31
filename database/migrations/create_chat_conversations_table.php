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
            // Auto-vertaling (zie 2026_07_31_130100_add_auto_translate_to_chat_conversations_table);
            // hier ook meteen opgenomen voor verse/testomgevingen (migratievolgorde).
            $table->boolean('auto_translate')->default(false);
            $table->string('agent_locale', 16)->nullable();
            $table->string('status')->default('active');   // active|closed
            $table->string('mode')->default('ai');         // ai|waiting_human|human
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->unsignedBigInteger('assigned_agent_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_role')->nullable();
            $table->timestamp('visitor_read_at')->nullable();
            $table->timestamp('visitor_last_active_at')->nullable();
            $table->string('visitor_session_token')->nullable()->index();
            $table->text('started_url')->nullable();
            $table->integer('order_lookup_attempts')->default(0);
            $table->string('ip_hash')->nullable();
            $table->string('visitor_ip')->nullable();
            $table->string('visitor_user_agent')->nullable();
            $table->string('visitor_referrer')->nullable();
            $table->string('visitor_country')->nullable();
            $table->string('visitor_city')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_conversations');
    }
};
