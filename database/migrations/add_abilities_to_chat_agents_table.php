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
        if (Schema::hasColumn('dashed__chat_agents', 'abilities')) {
            return;
        }
        Schema::table('dashed__chat_agents', function (Blueprint $table) {
            // Livechat-rechten van een medewerker (human agent): subset van
            // chat.read|chat.reply|chat.takeover|chat.manage. Null = standaardset.
            $table->json('abilities')->nullable()->after('enabled_tools');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('dashed__chat_agents') && Schema::hasColumn('dashed__chat_agents', 'abilities')) {
            Schema::table('dashed__chat_agents', function (Blueprint $table) {
                $table->dropColumn('abilities');
            });
        }
    }
};
