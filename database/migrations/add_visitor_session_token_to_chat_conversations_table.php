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
            if (! Schema::hasColumn('dashed__chat_conversations', 'visitor_session_token')) {
                $table->string('visitor_session_token')->nullable()->index()->after('visitor_last_active_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_conversations')) {
            return;
        }
        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__chat_conversations', 'visitor_session_token')) {
                $table->dropColumn('visitor_session_token');
            }
        });
    }
};
