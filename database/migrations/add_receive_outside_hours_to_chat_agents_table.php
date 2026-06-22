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

        Schema::table('dashed__chat_agents', function (Blueprint $table): void {
            if (! Schema::hasColumn('dashed__chat_agents', 'receive_outside_hours')) {
                // Mag deze (human) agent ook buiten openingstijden chats/handoffs ontvangen?
                $table->boolean('receive_outside_hours')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_agents')) {
            return;
        }

        Schema::table('dashed__chat_agents', function (Blueprint $table): void {
            if (Schema::hasColumn('dashed__chat_agents', 'receive_outside_hours')) {
                $table->dropColumn('receive_outside_hours');
            }
        });
    }
};
