<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('dashed__chat_conversations', 'started_url')) {
            Schema::table('dashed__chat_conversations', function (Blueprint $table) {
                $table->text('started_url')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__chat_conversations', 'started_url')) {
            Schema::table('dashed__chat_conversations', function (Blueprint $table) {
                $table->string('started_url')->nullable()->change();
            });
        }
    }
};
