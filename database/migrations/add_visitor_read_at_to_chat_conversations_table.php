<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_conversations')) {
            return;
        }
        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__chat_conversations', 'visitor_read_at')) {
                $table->timestamp('visitor_read_at')->nullable()->after('last_message_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            $table->dropColumn('visitor_read_at');
        });
    }
};
