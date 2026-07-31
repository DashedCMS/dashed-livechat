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
            if (! Schema::hasColumn('dashed__chat_conversations', 'auto_translate')) {
                $table->boolean('auto_translate')->default(false);
            }
            if (! Schema::hasColumn('dashed__chat_conversations', 'agent_locale')) {
                $table->string('agent_locale', 16)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_conversations')) {
            return;
        }

        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            foreach (['auto_translate', 'agent_locale'] as $column) {
                if (Schema::hasColumn('dashed__chat_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
