<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('dashed__chat_quick_replies')) {
            return;
        }
        Schema::table('dashed__chat_quick_replies', function (Blueprint $table) {
            if (! Schema::hasColumn('dashed__chat_quick_replies', 'shortcut')) {
                $table->string('shortcut')->nullable()->after('title');
            }
            if (! Schema::hasColumn('dashed__chat_quick_replies', 'owner_id')) {
                $table->unsignedBigInteger('owner_id')->nullable()->index()->after('sort');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('dashed__chat_quick_replies')) {
            return;
        }
        Schema::table('dashed__chat_quick_replies', function (Blueprint $table) {
            foreach (['shortcut', 'owner_id'] as $column) {
                if (Schema::hasColumn('dashed__chat_quick_replies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
