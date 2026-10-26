<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        $missing = [
            'team_id' => fn (Blueprint $table) => $table->unsignedBigInteger('team_id')->nullable()->index(),
            'role' => fn (Blueprint $table) => $table->string('role', 20)->default('closer')->index(),
            'active' => fn (Blueprint $table) => $table->boolean('active')->default(true)->index(),
            'phone' => fn (Blueprint $table) => $table->string('phone', 40)->nullable(),
            'avatar_color' => fn (Blueprint $table) => $table->string('avatar_color', 20)->nullable(),
            'last_login_at' => fn (Blueprint $table) => $table->timestamp('last_login_at')->nullable(),
        ];

        foreach ($missing as $column => $definition) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }

        if (Schema::hasColumn('users', 'team_id') && DB::getDriverName() === 'pgsql') {
            $foreignExists = DB::selectOne(
                "select 1 from information_schema.table_constraints where constraint_name = 'users_team_id_foreign' and table_name = 'users'"
            );

            if (! $foreignExists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $foreignExists = DB::selectOne(
                "select 1 from information_schema.table_constraints where constraint_name = 'users_team_id_foreign' and table_name = 'users'"
            );

            if ($foreignExists) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropForeign(['team_id']);
                });
            }
        }

        foreach (['last_login_at', 'avatar_color', 'phone', 'active', 'role', 'team_id'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        Schema::dropIfExists('teams');
    }
};
