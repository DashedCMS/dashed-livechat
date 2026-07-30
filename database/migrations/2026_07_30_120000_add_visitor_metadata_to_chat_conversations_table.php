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
            if (! Schema::hasColumn('dashed__chat_conversations', 'visitor_ip')) {
                $table->string('visitor_ip')->nullable()->after('ip_hash');
            }
            if (! Schema::hasColumn('dashed__chat_conversations', 'visitor_user_agent')) {
                $table->string('visitor_user_agent')->nullable()->after('visitor_ip');
            }
            if (! Schema::hasColumn('dashed__chat_conversations', 'visitor_referrer')) {
                $table->string('visitor_referrer')->nullable()->after('visitor_user_agent');
            }
            if (! Schema::hasColumn('dashed__chat_conversations', 'visitor_country')) {
                $table->string('visitor_country')->nullable()->after('visitor_referrer');
            }
            if (! Schema::hasColumn('dashed__chat_conversations', 'visitor_city')) {
                $table->string('visitor_city')->nullable()->after('visitor_country');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_conversations')) {
            return;
        }
        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            foreach (['visitor_ip', 'visitor_user_agent', 'visitor_referrer', 'visitor_country', 'visitor_city'] as $column) {
                if (Schema::hasColumn('dashed__chat_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
