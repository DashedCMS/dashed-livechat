<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
