<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_triggers')) {
            return;
        }

        Schema::table('dashed__chat_triggers', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__chat_triggers', 'min_page_views')) {
                $table->unsignedInteger('min_page_views')->nullable()->after('trigger_value');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_triggers')) {
            return;
        }

        Schema::table('dashed__chat_triggers', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__chat_triggers', 'min_page_views')) {
                $table->dropColumn('min_page_views');
            }
        });
    }
};
