<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_agents')) {
            return;
        }
        Schema::table('dashed__chat_agents', function (Blueprint $table) {
            foreach ([
                'escalate_on_request' => true,
                'escalate_on_negative' => true,
                'escalate_on_tool_failure' => true,
                'escalate_off_topic' => false,
            ] as $col => $default) {
                if (! Schema::hasColumn('dashed__chat_agents', $col)) {
                    $table->boolean($col)->default($default)->after('escalation_rules');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_agents')) {
            return;
        }
        Schema::table('dashed__chat_agents', function (Blueprint $table) {
            foreach (['escalate_on_request', 'escalate_on_negative', 'escalate_on_tool_failure', 'escalate_off_topic'] as $col) {
                if (Schema::hasColumn('dashed__chat_agents', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
