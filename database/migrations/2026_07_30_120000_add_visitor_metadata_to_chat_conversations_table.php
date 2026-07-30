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
            $table->string('visitor_ip')->nullable()->after('ip_hash');
            $table->string('visitor_user_agent')->nullable()->after('visitor_ip');
            $table->string('visitor_referrer')->nullable()->after('visitor_user_agent');
            $table->string('visitor_country')->nullable()->after('visitor_referrer');
            $table->string('visitor_city')->nullable()->after('visitor_country');
        });
    }

    public function down(): void
    {
        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['visitor_ip', 'visitor_user_agent', 'visitor_referrer', 'visitor_country', 'visitor_city']);
        });
    }
};
