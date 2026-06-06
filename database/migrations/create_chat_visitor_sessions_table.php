<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_visitor_sessions')) {
            return;
        }

        Schema::create('dashed__chat_visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('site_id')->index();
            $table->string('token')->index();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('url')->nullable();
            $table->string('referrer')->nullable();
            $table->string('ip_hash')->nullable();
            $table->string('country')->nullable();
            $table->string('country_code', 8)->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('cart_total', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_visitor_sessions');
    }
};
