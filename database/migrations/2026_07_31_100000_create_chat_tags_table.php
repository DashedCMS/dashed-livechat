<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_tags')) {
            return;
        }

        Schema::create('dashed__chat_tags', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('name');
            $table->string('color')->default('#64748b');
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_tags');
    }
};
