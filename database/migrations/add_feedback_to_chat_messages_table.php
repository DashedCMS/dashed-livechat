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
        Schema::table('dashed__chat_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__chat_messages', 'feedback')) {
                $table->string('feedback')->nullable()->after('is_internal');
            }
            if (! Schema::hasColumn('dashed__chat_messages', 'feedback_note')) {
                $table->text('feedback_note')->nullable()->after('feedback');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dashed__chat_messages', function (Blueprint $table) {
            $table->dropColumn(['feedback', 'feedback_note']);
        });
    }
};
