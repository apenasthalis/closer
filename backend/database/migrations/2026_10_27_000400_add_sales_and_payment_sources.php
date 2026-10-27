<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_sources')) {
            Schema::create('payment_sources', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug', 40)->unique();
                $table->string('name', 80);
                $table->boolean('enters_cash')->default(true)->index();
                $table->boolean('active')->default(true)->index();
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        $sources = [
            ['slug' => 'boleto', 'name' => 'Boleto', 'enters_cash' => false, 'position' => 1],
            ['slug' => 'pix', 'name' => 'Pix', 'enters_cash' => true, 'position' => 2],
            ['slug' => 'credit_card', 'name' => 'Cartão de crédito', 'enters_cash' => true, 'position' => 3],
            ['slug' => 'debit_card', 'name' => 'Cartão de débito', 'enters_cash' => true, 'position' => 4],
            ['slug' => 'contrato_parcelas', 'name' => 'Contrato com parcelas', 'enters_cash' => true, 'position' => 5],
            ['slug' => 'transfer', 'name' => 'Transferência', 'enters_cash' => true, 'position' => 6],
            ['slug' => 'cash', 'name' => 'Dinheiro', 'enters_cash' => true, 'position' => 7],
            ['slug' => 'other', 'name' => 'Outro', 'enters_cash' => true, 'position' => 8],
        ];

        DB::table('payment_sources')->insertOrIgnore(array_map(fn (array $source) => $source + [
            'id' => (string) Str::uuid(),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $sources));

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'enters_cash')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->boolean('enters_cash')->default(true)->index();
            });
        }

        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'next_action')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->text('next_action')->nullable();
                $table->timestamp('next_action_at')->nullable();
            });
        }

        if (Schema::hasTable('leads') && ! Schema::hasColumn('leads', 'commission_percentage')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->decimal('commission_percentage', 5, 2)->nullable();
                $table->decimal('commission_amount', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'next_action')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn(['next_action', 'next_action_at']);
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'enters_cash')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('enters_cash');
            });
        }

        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'commission_percentage')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn(['commission_percentage', 'commission_amount']);
            });
        }

        Schema::dropIfExists('payment_sources');
    }
};
