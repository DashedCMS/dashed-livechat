<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_triggers')) {
            return;
        }
        Schema::create('dashed__chat_triggers', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->string('placement')->default('all_pages'); // all_pages|include_urls|url_pattern
            $table->json('url_rules')->nullable();
            $table->json('exclude_urls')->nullable();
            $table->string('trigger_type')->default('none');   // none|immediate|time_on_page|scroll_depth|exit_intent
            $table->integer('trigger_value')->nullable();
            $table->text('proactive_message')->nullable();
            $table->unsignedBigInteger('ai_agent_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_triggers');
    }
};
