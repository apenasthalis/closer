<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'meeting_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->timestamp('meeting_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'meeting_at')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('meeting_at');
            });
        }
    }
};
