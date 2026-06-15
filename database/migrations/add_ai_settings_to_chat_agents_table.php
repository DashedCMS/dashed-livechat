<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_agents')) {
            return;
        }
        Schema::table('dashed__chat_agents', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__chat_agents', 'ai_reply_delay_seconds')) {
                $table->unsignedSmallInteger('ai_reply_delay_seconds')->default(8)->after('temperature');
            }
            if (! Schema::hasColumn('dashed__chat_agents', 'max_tokens')) {
                $table->unsignedSmallInteger('max_tokens')->default(1536)->after('ai_reply_delay_seconds');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_agents')) {
            return;
        }
        Schema::table('dashed__chat_agents', function (Blueprint $table) {
            if (Schema::hasColumn('dashed__chat_agents', 'ai_reply_delay_seconds')) {
                $table->dropColumn('ai_reply_delay_seconds');
            }
            if (Schema::hasColumn('dashed__chat_agents', 'max_tokens')) {
                $table->dropColumn('max_tokens');
            }
        });
    }
};
