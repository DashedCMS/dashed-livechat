<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_agents')) {
            return;
        }
        Schema::create('dashed__chat_agents', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('type')->default('ai');           // ai|human
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('receive_outside_hours')->default(false); // human: ook buiten openingstijden ontvangen
            $table->integer('sort_order')->default(0);
            $table->string('email')->nullable();              // human
            $table->unsignedBigInteger('user_id')->nullable(); // human -> users.id
            $table->text('persona')->nullable();
            $table->string('tone')->nullable();
            $table->json('languages')->nullable();
            $table->text('allowed_topics')->nullable();
            $table->text('disallowed_topics')->nullable();
            $table->text('escalation_rules')->nullable();
            $table->text('greeting')->nullable();
            $table->string('model')->default('claude-sonnet-4-6');
            $table->decimal('temperature', 3, 2)->default(0.50);
            $table->unsignedSmallInteger('ai_reply_delay_seconds')->default(8);
            $table->unsignedSmallInteger('max_tokens')->default(1536);
            $table->string('guardrail_mode')->default('standard'); // standard|strict
            $table->json('enabled_tools')->nullable();
            // Ook hier, en niet alleen in add_abilities_to_chat_agents_table:
            // Laravel sorteert migraties alfabetisch, dus die add-migratie draait
            // vóór deze create en valt stil op zijn hasTable-guard.
            $table->json('abilities')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_agents');
    }
};
