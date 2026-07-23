<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__web_push_preferences')) {
            return;
        }

        Schema::create('dashed__web_push_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('site_id');
            $table->boolean('notify_handoff')->default(true);
            $table->boolean('notify_message')->default(true);
            $table->boolean('notify_new')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__web_push_preferences');
    }
};
