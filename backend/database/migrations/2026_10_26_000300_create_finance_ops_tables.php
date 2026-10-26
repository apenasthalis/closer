<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('playbooks')) {
            Schema::create('playbooks', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('niche_id')->nullable()->constrained('niches')->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->json('criteria');
                $table->unsignedInteger('version')->default(1);
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('scripts') && Schema::hasColumn('scripts', 'playbook_id')) {
            try {
                Schema::table('scripts', function (Blueprint $table) {
                    $table->foreign('playbook_id')->references('id')->on('playbooks')->nullOnDelete();
                });
            } catch (Throwable) {
                // A constraint já existe ou o driver atual não suporta ALTER de FK.
            }
        }

        if (! Schema::hasTable('proposals')) {
            Schema::create('proposals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('number')->unique();
                $table->string('title');
                $table->string('status', 20)->default('draft')->index();
                $table->date('valid_until')->nullable();
                $table->string('currency', 3)->default('BRL');
                $table->json('items');
                $table->decimal('subtotal', 12, 2)->default(0);
                $table->decimal('discount', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->text('payment_terms')->nullable();
                $table->json('clauses')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['lead_id', 'status']);
            });
        }

        if (! Schema::hasTable('proposal_versions')) {
            Schema::create('proposal_versions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('proposal_id')->constrained('proposals')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->json('snapshot');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['proposal_id', 'version']);
            });
        }

        if (! Schema::hasTable('contracts')) {
            Schema::create('contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
                $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('number')->unique();
                $table->string('title');
                $table->string('status', 20)->default('draft')->index();
                $table->decimal('total', 12, 2)->default(0);
                $table->longText('content');
                $table->timestamp('signed_at')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignUuid('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
                $table->foreignUuid('proposal_id')->nullable()->constrained('proposals')->nullOnDelete();
                $table->string('kind', 24)->default('installment');
                $table->unsignedInteger('installment_number')->default(1);
                $table->decimal('amount', 12, 2);
                $table->date('due_at')->index();
                $table->timestamp('paid_at')->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->string('method', 24)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commission_rules')) {
            Schema::create('commission_rules', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('scope', 20)->default('global')->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
                $table->foreignUuid('niche_id')->nullable()->constrained('niches')->nullOnDelete();
                $table->decimal('percentage', 5, 2)->nullable();
                $table->decimal('fixed_amount', 12, 2)->nullable();
                $table->string('basis', 20)->default('contracted');
                $table->boolean('active')->default(true)->index();
                $table->unsignedInteger('priority')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('commissions')) {
            Schema::create('commissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignUuid('rule_id')->nullable()->constrained('commission_rules')->nullOnDelete();
                $table->json('rule_snapshot');
                $table->decimal('sale_amount', 12, 2)->default(0);
                $table->decimal('received_amount', 12, 2)->default(0);
                $table->decimal('percentage', 5, 2)->nullable();
                $table->decimal('fixed_amount', 12, 2)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('status', 20)->default('estimated')->index();
                $table->string('period', 7)->index();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['lead_id', 'user_id', 'period']);
            });
        }

        if (! Schema::hasTable('auth_sessions')) {
            Schema::create('auth_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->char('refresh_token_hash', 64)->unique();
                $table->timestamp('expires_at')->index();
                $table->timestamp('revoked_at')->nullable();
                $table->unsignedBigInteger('replaced_by_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->string('revoked_reason', 40)->nullable();
                $table->timestamps();
                $table->index(['user_id', 'revoked_at']);
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 80);
                $table->string('entity_type', 120)->nullable();
                $table->string('entity_id', 64)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();
                $table->index(['entity_type', 'entity_id']);
            });
        }

        if (! Schema::hasTable('integration_settings')) {
            Schema::create('integration_settings', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 40)->unique();
                $table->boolean('enabled')->default(false);
                $table->string('status', 24)->default('not_configured');
                $table->json('config')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_logs')) {
            Schema::create('notification_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('channel', 32);
                $table->string('recipient')->nullable();
                $table->string('subject')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->json('payload')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->index(['channel', 'created_at']);
            });
        }

        if (! Schema::hasTable('report_runs')) {
            Schema::create('report_runs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type', 32)->default('weekly');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('generated');
                $table->json('metrics');
                $table->json('recipients')->nullable();
                $table->text('error')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['type', 'period_start']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'report_runs', 'notification_logs', 'integration_settings', 'audit_logs', 'auth_sessions',
            'commissions', 'commission_rules', 'payments', 'contracts', 'proposal_versions',
            'proposals', 'playbooks',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
