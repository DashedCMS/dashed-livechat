<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('dashed__chat_triggers') && ! Schema::hasColumn('dashed__chat_triggers', 'model_links')) {
            Schema::table('dashed__chat_triggers', function (Blueprint $table) {
                $table->json('model_links')->nullable()->after('exclude_urls');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__chat_triggers', 'model_links')) {
            Schema::table('dashed__chat_triggers', function (Blueprint $table) {
                $table->dropColumn('model_links');
            });
        }
    }
};
