<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\User;
use App\Services\CallAnalysisService;
use App\Services\CommissionService;
use App\Services\Documents\ContractPdfService;
use App\Services\Documents\ProposalPdfService;
use App\Services\ReminderAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnalysisAndFinanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_call_analysis_extracts_spin_objections_and_next_steps(): void
    {
        $closer = User::factory()->create(['name' => 'Closer']);
        $lead = Lead::query()->create(['name' => 'Lead', 'stage' => 'negociacao', 'closer_id' => $closer->id]);

        $call = Call::query()->create([
            'lead_id' => $lead->id,
            'closer_id' => $closer->id,
            'title' => 'Diagnóstico',
            'status' => 'completed',
            'started_at' => now(),
            'transcript' => ['text' => implode("\n", [
                'Closer: Olá, nosso objetivo hoje é entender a situação e o problema atual.',
                'Lead: Hoje temos dificuldade com estoque e perdemos vendas. O impacto é de 10% do faturamento.',
                'Closer: Se resolvesse isso, o que mudaria?',
                'Lead: Eu teria previsibilidade e conseguiria crescer. Mas está caro e preciso pensar.',
                'Closer: Entendo. Vou enviar a proposta e marcamos o retorno na quinta.',
                'Lead: Pode enviar, ficamos combinados.',
            ])],
        ]);

        $analyzed = app(CallAnalysisService::class)->analyze($call, $closer);

        $this->assertSame('local-heuristic', $analyzed->ai_provider);
        $this->assertSame('completed', $analyzed->analysis_status);
        $this->assertNotNull($analyzed->summary);
        $this->assertTrue($analyzed->spin['problema']['identified']);
        $this->assertNotEmpty($analyzed->objections);
        $this->assertNotEmpty($analyzed->extracted_steps);
        $this->assertGreaterThan(0, $analyzed->playbook_score);
        $this->assertDatabaseHas('lead_interactions', ['lead_id' => $lead->id, 'kind' => 'analise_call']);
    }

    public function test_call_analysis_rejects_empty_transcript(): void
    {
        $call = Call::query()->create([
            'title' => 'Sem transcrição',
            'status' => 'completed',
            'started_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(CallAnalysisService::class)->analyze($call);
    }

    public function test_commission_calculation_is_snapshotted_and_self_approval_is_blocked(): void
    {
        $closer = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lead = Lead::query()->create([
            'name' => 'Fechado',
            'stage' => 'fechado',
            'closer_id' => $closer->id,
            'ticket_fechado' => 10000,
            'last_stage_change_at' => now(),
        ]);

        CommissionRule::query()->create([
            'name' => 'Padrão',
            'scope' => 'global',
            'percentage' => 10,
            'basis' => 'contracted',
            'active' => true,
            'priority' => 0,
        ]);

        $service = app(CommissionService::class);
        $commission = $service->calculateForLead($lead, now()->format('Y-m'));

        $this->assertInstanceOf(Commission::class, $commission);
        $this->assertEquals(1000, (float) $commission->amount);
        $this->assertSame('calculated', $commission->status);
        $this->assertArrayHasKey('rule_id', $commission->rule_snapshot);

        $this->expectException(ValidationException::class);
        $service->approve($commission, $closer);
    }

    public function test_commission_approval_and_payment_flow(): void
    {
        $closer = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lead = Lead::query()->create([
            'name' => 'Fechado',
            'stage' => 'fechado',
            'closer_id' => $closer->id,
            'ticket_fechado' => 5000,
            'last_stage_change_at' => now(),
        ]);
        CommissionRule::query()->create([
            'name' => 'Padrão', 'scope' => 'global', 'percentage' => 5,
            'basis' => 'contracted', 'active' => true, 'priority' => 0,
        ]);

        $service = app(CommissionService::class);
        $commission = $service->calculateForLead($lead, now()->format('Y-m'));
        $approved = $service->approve($commission, $admin);
        $this->assertSame('approved', $approved->status);

        $paid = $service->markPaid($approved, $admin);
        $this->assertSame('paid', $paid->status);
        $this->assertEquals(250, (float) $paid->amount);
    }

    public function test_proposal_and_contract_pdf_are_valid_pdf_documents(): void
    {
        $user = User::factory()->create();
        $lead = Lead::query()->create(['name' => 'Cliente PDF', 'closer_id' => $user->id]);

        $proposal = Proposal::query()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'number' => 'PROP-TEST-1',
            'title' => 'Proposta teste',
            'status' => 'draft',
            'items' => [['description' => 'Serviço', 'quantity' => 1, 'unit_price' => 1000]],
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'version' => 1,
        ]);

        $proposalPdf = app(ProposalPdfService::class)->render($proposal);
        $this->assertStringStartsWith('%PDF-1.4', $proposalPdf);
        $this->assertStringContainsString('%%EOF', $proposalPdf);

        $contract = Contract::query()->create([
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'number' => 'CTR-TEST-1',
            'title' => 'Contrato teste',
            'status' => 'draft',
            'total' => 1000,
            'content' => 'Conteúdo do contrato de teste com cláusulas suficientes.',
        ]);

        $contractPdf = app(ContractPdfService::class)->render($contract);
        $this->assertStringStartsWith('%PDF-1.4', $contractPdf);
    }

    public function test_reminder_automation_is_idempotent(): void
    {
        $closer = User::factory()->create();
        Lead::query()->create([
            'name' => 'Lead parado',
            'stage' => 'fup',
            'closer_id' => $closer->id,
            'last_interaction_at' => now()->subDays(5),
        ]);

        $service = app(ReminderAutomationService::class);
        $first = $service->run();
        $second = $service->run();

        $this->assertGreaterThanOrEqual(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseCount('reminders', 1);
    }

    public function test_payments_index_loads_proposal_relation(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lead = Lead::query()->create(['name' => 'Pagamento com proposta', 'closer_id' => $admin->id]);

        $proposal = Proposal::query()->create([
            'lead_id' => $lead->id,
            'created_by' => $admin->id,
            'number' => 'PROP-PAY-1',
            'title' => 'Proposta vinculada',
            'status' => 'draft',
            'items' => [['description' => 'Serviço', 'quantity' => 1, 'unit_price' => 100]],
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'version' => 1,
        ]);

        Payment::query()->create([
            'lead_id' => $lead->id,
            'proposal_id' => $proposal->id,
            'kind' => 'installment',
            'installment_number' => 1,
            'amount' => 100,
            'due_at' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $token = app(\App\Services\Auth\JwtService::class)->encode([
            'sub' => (string) $admin->id,
            'role' => $admin->role,
            'typ' => 'access',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonPath('data.0.proposal.number', 'PROP-PAY-1')
            ->assertJsonPath('data.0.lead.name', 'Pagamento com proposta');
    }

    public function test_payment_installments_split_cents_without_losing_value(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $lead = Lead::query()->create(['name' => 'Parcelado', 'closer_id' => $user->id]);

        $token = app(\App\Services\Auth\JwtService::class)->encode([
            'sub' => (string) $user->id,
            'role' => $user->role,
            'typ' => 'access',
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/leads/'.$lead->id.'/payments/installments', [
                'total' => 100,
                'installments' => 3,
                'first_due_at' => now()->addDay()->toDateString(),
            ])
            ->assertCreated();

        $sum = Payment::query()->sum('amount');
        $this->assertEquals(100.0, (float) $sum);
        $this->assertDatabaseCount('payments', 3);
    }
}
