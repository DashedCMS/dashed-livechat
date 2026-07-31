<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_unanswered_questions')) {
            return;
        }

        Schema::create('dashed__chat_unanswered_questions', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->unsignedBigInteger('chat_conversation_id')->nullable()->index();
            $table->text('question');
            $table->string('reason')->default('handoff'); // handoff|negative_feedback|no_match
            $table->string('status')->default('open')->index(); // open|resolved
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_unanswered_questions');
    }
};
