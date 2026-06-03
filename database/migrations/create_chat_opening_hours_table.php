<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_opening_hours')) {
            return;
        }
        Schema::create('dashed__chat_opening_hours', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0=zo..6=za (Carbon dayOfWeek); gevuld = wekelijkse regel
            $table->date('date')->nullable();                       // gevuld = uitzondering/feestdag
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_opening_hours');
    }
};
