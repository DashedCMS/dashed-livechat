<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_quick_replies')) {
            return;
        }
        Schema::create('dashed__chat_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('title');
            $table->text('content');
            $table->integer('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_quick_replies');
    }
};
