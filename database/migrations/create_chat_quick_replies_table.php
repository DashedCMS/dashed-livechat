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
            // `shortcut`/`owner_id` zijn later toegevoegd (zie
            // 2026_07_31_090000_add_shortcut_and_owner_to_chat_quick_replies_table);
            // hier ook meteen opgenomen zodat een verse install/testomgeving ze
            // direct heeft, ongeacht de (alfabetische) migratievolgorde.
            $table->string('shortcut')->nullable();
            $table->text('content');
            $table->integer('sort')->default(0);
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashed__chat_quick_replies');
    }
};
