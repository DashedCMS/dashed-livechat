<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__app_notifications')) {
            return;
        }

        Schema::create('dashed__app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('channel')->index(); // bv. visitors
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__app_notifications');
    }
};
