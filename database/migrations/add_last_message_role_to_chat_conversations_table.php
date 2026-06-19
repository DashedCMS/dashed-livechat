<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_conversations')) {
            return;
        }

        if (! Schema::hasColumn('dashed__chat_conversations', 'last_message_role')) {
            Schema::table('dashed__chat_conversations', function (Blueprint $table) {
                $table->string('last_message_role')->nullable()->after('last_message_at');
            });
        }

        // Backfill bestaande gesprekken: zet de rol van het laatste niet-interne
        // bericht (bepaalt 'wie is aan de beurt'). Eén UPDATE met correlated subquery.
        if (Schema::hasTable('dashed__chat_messages')) {
            DB::statement(<<<'SQL'
                UPDATE dashed__chat_conversations c
                SET last_message_role = (
                    SELECT m.role
                    FROM dashed__chat_messages m
                    WHERE m.chat_conversation_id = c.id
                      AND m.is_internal = 0
                      AND m.role IN ('visitor', 'ai', 'human')
                    ORDER BY m.id DESC
                    LIMIT 1
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            $table->dropColumn('last_message_role');
        });
    }
};
