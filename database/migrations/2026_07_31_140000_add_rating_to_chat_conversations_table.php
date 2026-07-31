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
            if (! Schema::hasColumn('dashed__chat_conversations', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable();
            }
            if (! Schema::hasColumn('dashed__chat_conversations', 'rating_comment')) {
                $table->text('rating_comment')->nullable();
            }
            if (! Schema::hasColumn('dashed__chat_conversations', 'rated_at')) {
                $table->timestamp('rated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_conversations')) {
            return;
        }

        Schema::table('dashed__chat_conversations', function (Blueprint $table) {
            foreach (['rating', 'rating_comment', 'rated_at'] as $column) {
                if (Schema::hasColumn('dashed__chat_conversations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
