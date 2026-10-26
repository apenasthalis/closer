<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('niches')) {
            Schema::create('niches', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('color')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            $this->addMissing('niches', [
                'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
                'active' => fn (Blueprint $table) => $table->boolean('active')->default(true),
            ]);
        }

        if (! Schema::hasTable('leads')) {
            Schema::create('leads', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('company')->nullable();
                $table->string('phone')->nullable();
                $table->string('whatsapp')->nullable();
                $table->string('email')->nullable();
                $table->foreignUuid('niche_id')->nullable()->constrained('niches')->nullOnDelete();
                $table->string('origin')->nullable();
                $table->string('stage', 32)->default('lead_agendado')->index();
                $table->string('temperature', 16)->default('warm')->index();
                $table->unsignedInteger('priority')->default(0);
                $table->unsignedInteger('position')->default(0);
                $table->foreignId('closer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('pre_sales_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('ticket_estimado', 12, 2)->nullable();
                $table->decimal('valor_negociado', 12, 2)->nullable();
                $table->decimal('ticket_fechado', 12, 2)->nullable();
                $table->decimal('receita_prevista', 12, 2)->nullable();
                $table->timestamp('first_contact_at')->nullable();
                $table->timestamp('last_interaction_at')->nullable()->index();
                $table->timestamp('next_action_at')->nullable()->index();
                $table->timestamp('last_stage_change_at')->nullable();
                $table->json('next_steps')->nullable();
                $table->text('loss_reason')->nullable();
                $table->text('disqualify_reason')->nullable();
                $table->string('call_id')->nullable();
                $table->decimal('saldo_devedor', 12, 2)->nullable();
                $table->json('parcelas')->nullable();
                $table->json('custom_fields')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['stage', 'position']);
            });
        } else {
            $this->addMissing('leads', [
                'company' => fn (Blueprint $table) => $table->string('company')->nullable(),
                'whatsapp' => fn (Blueprint $table) => $table->string('whatsapp')->nullable(),
                'pre_sales_id' => fn (Blueprint $table) => $table->unsignedBigInteger('pre_sales_id')->nullable()->index(),
                'valor_negociado' => fn (Blueprint $table) => $table->decimal('valor_negociado', 12, 2)->nullable(),
                'receita_prevista' => fn (Blueprint $table) => $table->decimal('receita_prevista', 12, 2)->nullable(),
                'priority' => fn (Blueprint $table) => $table->unsignedInteger('priority')->default(0)->index(),
                'position' => fn (Blueprint $table) => $table->unsignedInteger('position')->default(0),
                'custom_fields' => fn (Blueprint $table) => $table->json('custom_fields')->nullable(),
                'last_stage_change_at' => fn (Blueprint $table) => $table->timestamp('last_stage_change_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('lead_interactions')) {
            Schema::create('lead_interactions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->string('kind', 32);
                $table->timestamp('occurred_at')->index();
                $table->string('author')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('summary');
                $table->string('call_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['lead_id', 'occurred_at']);
            });
        } else {
            $this->addMissing('lead_interactions', [
                'user_id' => fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable()->index(),
                'metadata' => fn (Blueprint $table) => $table->json('metadata')->nullable(),
            ]);
        }

        if (! Schema::hasTable('lead_stage_histories')) {
            Schema::create('lead_stage_histories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->string('from_stage', 32)->nullable();
                $table->string('to_stage', 32);
                $table->text('reason')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['lead_id', 'created_at']);
            });
        } else {
            $this->addMissing('lead_stage_histories', [
                'metadata' => fn (Blueprint $table) => $table->json('metadata')->nullable(),
            ]);
        }

        if (! Schema::hasTable('reminders')) {
            Schema::create('reminders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->string('type', 32);
                $table->string('title');
                $table->text('detail')->nullable();
                $table->timestamp('due_at')->index();
                $table->string('status', 16)->default('pendente')->index();
                $table->boolean('sent_to_lead')->default(false);
                $table->integer('priority')->default(0);
                $table->timestamp('snoozed_from')->nullable();
                $table->string('origin', 32)->default('manual');
                $table->string('rule_key')->nullable();
                $table->string('dedupe_key')->nullable()->unique();
                $table->json('rule_meta')->nullable();
                $table->unsignedInteger('rescheduled_count')->default(0);
                $table->timestamp('last_rule_run_at')->nullable();
                $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['responsible_id', 'status', 'due_at']);
            });
        } else {
            $this->addMissing('reminders', [
                'rule_key' => fn (Blueprint $table) => $table->string('rule_key')->nullable()->index(),
                'dedupe_key' => fn (Blueprint $table) => $table->string('dedupe_key')->nullable()->unique(),
                'rule_meta' => fn (Blueprint $table) => $table->json('rule_meta')->nullable(),
                'rescheduled_count' => fn (Blueprint $table) => $table->unsignedInteger('rescheduled_count')->default(0),
                'last_rule_run_at' => fn (Blueprint $table) => $table->timestamp('last_rule_run_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('calls')) {
            Schema::create('calls', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->foreignId('closer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('title')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->string('status', 20)->default('completed')->index();
                $table->integer('duration_sec')->default(0);
                $table->json('transcript')->nullable();
                $table->text('summary')->nullable();
                $table->json('spin')->nullable();
                $table->json('objections')->nullable();
                $table->decimal('talk_time_closer', 5, 4)->nullable();
                $table->decimal('talk_time_lead', 5, 4)->nullable();
                $table->smallInteger('adherence_score')->nullable();
                $table->smallInteger('playbook_score')->nullable();
                $table->json('analysis')->nullable();
                $table->string('analysis_status', 20)->default('pending');
                $table->string('ai_provider')->nullable();
                $table->timestamp('analyzed_at')->nullable();
                $table->string('recording_path')->nullable();
                $table->string('recording_disk')->nullable();
                $table->json('sentiment')->nullable();
                $table->json('highlights')->nullable();
                $table->json('extracted_steps')->nullable();
                $table->timestamps();
                $table->index(['lead_id', 'started_at']);
            });
        } else {
            $this->addMissing('calls', [
                'title' => fn (Blueprint $table) => $table->string('title')->nullable(),
                'status' => fn (Blueprint $table) => $table->string('status', 20)->default('completed')->index(),
                'talk_time_lead' => fn (Blueprint $table) => $table->decimal('talk_time_lead', 5, 4)->nullable(),
                'playbook_score' => fn (Blueprint $table) => $table->smallInteger('playbook_score')->nullable(),
                'analysis' => fn (Blueprint $table) => $table->json('analysis')->nullable(),
                'analysis_status' => fn (Blueprint $table) => $table->string('analysis_status', 20)->default('pending'),
                'ai_provider' => fn (Blueprint $table) => $table->string('ai_provider')->nullable(),
                'analyzed_at' => fn (Blueprint $table) => $table->timestamp('analyzed_at')->nullable(),
                'recording_path' => fn (Blueprint $table) => $table->string('recording_path')->nullable(),
                'recording_disk' => fn (Blueprint $table) => $table->string('recording_disk')->nullable(),
            ]);
        }

        if (! Schema::hasTable('scripts')) {
            Schema::create('scripts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('niche_id')->nullable()->constrained('niches')->nullOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('stage', 32)->nullable();
                $table->string('category', 32)->nullable();
                $table->json('sections')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->string('status', 16)->default('draft');
                $table->unsignedBigInteger('playbook_id')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['niche_id', 'stage', 'status']);
            });
        } else {
            $this->addMissing('scripts', [
                'stage' => fn (Blueprint $table) => $table->string('stage', 32)->nullable(),
                'category' => fn (Blueprint $table) => $table->string('category', 32)->nullable(),
                'version' => fn (Blueprint $table) => $table->unsignedInteger('version')->default(1),
                'status' => fn (Blueprint $table) => $table->string('status', 16)->default('draft'),
                'playbook_id' => fn (Blueprint $table) => $table->unsignedBigInteger('playbook_id')->nullable(),
                'updated_by' => fn (Blueprint $table) => $table->unsignedBigInteger('updated_by')->nullable(),
                'published_at' => fn (Blueprint $table) => $table->timestamp('published_at')->nullable(),
            ]);
        }

        if (! Schema::hasTable('objections')) {
            Schema::create('objections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('niche_id')->nullable()->constrained('niches')->nullOnDelete();
                $table->string('label');
                $table->string('category', 32)->nullable();
                $table->string('stage', 32)->nullable();
                $table->text('context')->nullable();
                $table->integer('times_raised')->default(0);
                $table->integer('times_resolved')->default(0);
                $table->text('what_worked')->nullable();
                $table->text('best_rebuttal')->nullable();
                $table->string('result', 32)->nullable();
                $table->foreignId('closer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('lead_id')->nullable()->constrained('leads')->nullOnDelete();
                $table->uuid('call_id')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
                $table->index(['niche_id', 'category', 'stage']);
            });
        } else {
            $this->addMissing('objections', [
                'category' => fn (Blueprint $table) => $table->string('category', 32)->nullable(),
                'stage' => fn (Blueprint $table) => $table->string('stage', 32)->nullable(),
                'context' => fn (Blueprint $table) => $table->text('context')->nullable(),
                'result' => fn (Blueprint $table) => $table->string('result', 32)->nullable(),
                'closer_id' => fn (Blueprint $table) => $table->unsignedBigInteger('closer_id')->nullable()->index(),
                'lead_id' => fn (Blueprint $table) => $table->uuid('lead_id')->nullable()->index(),
                'call_id' => fn (Blueprint $table) => $table->uuid('call_id')->nullable(),
            ]);
        }

        if (! Schema::hasTable('lead_attachments')) {
            Schema::create('lead_attachments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('lead_id')->constrained('leads')->cascadeOnDelete();
                $table->string('kind', 32);
                $table->string('file_name');
                $table->integer('size_kb')->default(0);
                $table->string('path')->nullable();
                $table->string('mime_type')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description')->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamps();
                $table->index(['lead_id', 'kind']);
            });
        } else {
            $this->addMissing('lead_attachments', [
                'mime_type' => fn (Blueprint $table) => $table->string('mime_type')->nullable(),
                'checksum' => fn (Blueprint $table) => $table->string('checksum', 64)->nullable(),
                'uploaded_by' => fn (Blueprint $table) => $table->unsignedBigInteger('uploaded_by')->nullable(),
                'description' => fn (Blueprint $table) => $table->text('description')->nullable(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (['lead_attachments', 'objections', 'scripts', 'calls', 'reminders', 'lead_stage_histories', 'lead_interactions', 'leads', 'niches'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * @param  array<string, callable(Blueprint):void>  $definitions
     */
    private function addMissing(string $table, array $definitions): void
    {
        $missing = [];

        foreach ($definitions as $column => $definition) {
            if (! Schema::hasColumn($table, $column)) {
                $missing[$column] = $definition;
            }
        }

        if ($missing === []) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($missing) {
            foreach ($missing as $definition) {
                $definition($blueprint);
            }
        });
    }
};
