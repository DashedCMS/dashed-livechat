<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_messages')) {
            return;
        }

        if (! Schema::hasColumn('dashed__chat_messages', 'attachments')) {
            Schema::table('dashed__chat_messages', function (Blueprint $table) {
                $table->json('attachments')->nullable()->after('content');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dashed__chat_messages', 'attachments')) {
            Schema::table('dashed__chat_messages', function (Blueprint $table) {
                $table->dropColumn('attachments');
            });
        }
    }
};
