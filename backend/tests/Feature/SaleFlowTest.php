<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Payment;
use App\Models\Reminder;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_sources_expose_cash_in_rules(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CLOSER]);

        $response = $this->withToken($this->token($user))
            ->getJson('/api/v1/payment-sources')
            ->assertOk();

        $sources = collect($response->json('data'))->keyBy('slug');

        $this->assertTrue($sources['boleto']['enters_cash'] === false);
        $this->assertTrue($sources['pix']['enters_cash'] === true);
        $this->assertTrue($sources['credit_card']['enters_cash'] === true);
        $this->assertTrue($sources['contrato_parcelas']['enters_cash'] === true);
    }

    public function test_sale_registers_commission_source_and_next_action(): void
    {
        $closer = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $lead = Lead::query()->create([
            'name' => 'Cliente da venda',
            'stage' => 'negociacao',
            'closer_id' => $closer->id,
        ]);

        $response = $this->withToken($this->token($closer))
            ->postJson('/api/v1/leads/'.$lead->id.'/sale', [
                'amount' => 2000,
                'source' => 'pix',
                'status' => 'paid',
                'due_at' => now()->toDateString(),
                'commission_percentage' => 15,
                'next_action' => 'Falar sobre a segunda parcela e o pix da entrada.',
                'next_action_at' => now()->addDay()->toDateString(),
            ])
            ->assertCreated();

        $this->assertSame('fechado', $response->json('data.lead.stage'));
        $this->assertSame('pix', $response->json('data.payment.method'));
        $this->assertTrue((bool) $response->json('data.payment.enters_cash'));
        $this->assertEquals(300, (float) $response->json('data.commission.amount'));

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'ticket_fechado' => 2000,
            'commission_percentage' => 15,
        ]);

        $this->assertDatabaseHas('payments', [
            'lead_id' => $lead->id,
            'method' => 'pix',
            'enters_cash' => true,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('reminders', [
            'lead_id' => $lead->id,
            'responsible_id' => $closer->id,
            'origin' => 'sale',
        ]);
    }

    public function test_boleto_sale_does_not_enter_cash(): void
    {
        $closer = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $lead = Lead::query()->create([
            'name' => 'Cliente boleto',
            'stage' => 'negociacao',
            'closer_id' => $closer->id,
        ]);

        $this->withToken($this->token($closer))
            ->postJson('/api/v1/leads/'.$lead->id.'/sale', [
                'amount' => 1000,
                'source' => 'boleto',
                'status' => 'paid',
                'due_at' => now()->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.payment.enters_cash', false);

        $this->assertDatabaseHas('payments', [
            'lead_id' => $lead->id,
            'method' => 'boleto',
            'enters_cash' => false,
        ]);
    }

    public function test_user_cannot_register_sale_on_another_users_lead(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $other = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $lead = Lead::query()->create([
            'name' => 'Lead alheio',
            'stage' => 'negociacao',
            'closer_id' => $owner->id,
        ]);

        $this->withToken($this->token($other))
            ->postJson('/api/v1/leads/'.$lead->id.'/sale', [
                'amount' => 100,
                'source' => 'pix',
                'due_at' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_users_with_short_password(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->withToken($this->token($admin))
            ->postJson('/api/v1/users', [
                'name' => 'Novo Closer',
                'email' => 'novo.closer@closer.local',
                'password' => '123456',
                'password_confirmation' => '123456',
                'role' => User::ROLE_CLOSER,
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', User::ROLE_CLOSER);

        $this->assertDatabaseHas('users', ['email' => 'novo.closer@closer.local']);
    }

    public function test_calls_reminders_and_commissions_are_isolated_per_user(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $other = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $lead = Lead::query()->create([
            'name' => 'Meu lead',
            'stage' => 'fechado',
            'closer_id' => $owner->id,
            'ticket_fechado' => 1000,
        ]);

        Reminder::query()->create([
            'lead_id' => $lead->id,
            'type' => 'follow_up',
            'title' => 'Tarefa minha',
            'due_at' => now()->addDay(),
            'status' => Reminder::STATUS_PENDING,
            'responsible_id' => $owner->id,
        ]);

        Payment::query()->create([
            'lead_id' => $lead->id,
            'kind' => 'entry',
            'amount' => 1000,
            'due_at' => now()->toDateString(),
            'status' => 'paid',
            'method' => 'pix',
            'created_by' => $owner->id,
        ]);

        $this->withToken($this->token($other))
            ->getJson('/api/v1/reminders')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->withToken($this->token($other))
            ->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($this->token($owner))
            ->getJson('/api/v1/reminders')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->withToken($this->token($owner))
            ->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    private function token(User $user): string
    {
        return app(JwtService::class)->encode([
            'sub' => (string) $user->id,
            'role' => $user->role,
            'typ' => 'access',
        ]);
    }
}
