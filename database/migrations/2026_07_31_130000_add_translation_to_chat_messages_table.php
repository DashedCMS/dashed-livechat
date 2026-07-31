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
            if (! Schema::hasColumn('dashed__chat_messages', 'translated_content')) {
                $table->text('translated_content')->nullable()->after('content');
            }
            if (! Schema::hasColumn('dashed__chat_messages', 'source_locale')) {
                $table->string('source_locale', 16)->nullable()->after('translated_content');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_messages')) {
            return;
        }

        Schema::table('dashed__chat_messages', function (Blueprint $table) {
            foreach (['translated_content', 'source_locale'] as $column) {
                if (Schema::hasColumn('dashed__chat_messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
