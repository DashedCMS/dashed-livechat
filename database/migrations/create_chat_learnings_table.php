<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_learnings')) {
            return;
        }
        Schema::create('dashed__chat_learnings', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->text('question');
            $table->text('answer');
            $table->string('source')->default('feedback'); // feedback|human|ai
            $table->unsignedBigInteger('source_message_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_learnings');
    }
};
