<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 120);
                $table->string('cnpj', 18)->nullable()->unique();
                $table->string('email', 180)->nullable();
                $table->string('phone', 30)->nullable();
                $table->text('address')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['active']);
            });
        }

        if (! Schema::hasTable('company_user')) {
            Schema::create('company_user', function (Blueprint $table) {
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->primary(['company_id', 'user_id']);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'company_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->uuid('company_id')->nullable()->index();
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'company_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('companies');
    }
};
