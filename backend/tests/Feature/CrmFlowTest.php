<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Reminder;
use App\Models\Team;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_closer_only_sees_own_leads_and_cannot_move_another_closer_lead(): void
    {
        $team = Team::query()->create(['name' => 'Comercial', 'slug' => 'comercial', 'active' => true]);
        $closerA = User::factory()->create(['role' => User::ROLE_CLOSER, 'team_id' => $team->id]);
        $closerB = User::factory()->create(['role' => User::ROLE_CLOSER, 'team_id' => $team->id]);

        $leadA = $this->lead(['name' => 'Lead A', 'closer_id' => $closerA->id]);
        $leadB = $this->lead(['name' => 'Lead B', 'closer_id' => $closerB->id]);

        $this->withToken($this->token($closerA))
            ->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $leadA->id);

        $this->withToken($this->token($closerA))
            ->patchJson('/api/v1/leads/'.$leadB->id.'/stage', [
                'stage' => 'negociacao',
            ])
            ->assertForbidden();
    }

    public function test_every_user_only_sees_own_leads_including_manager_and_admin(): void
    {
        $team = Team::query()->create(['name' => 'Comercial', 'slug' => 'comercial', 'active' => true]);
        $otherTeam = Team::query()->create(['name' => 'Outro', 'slug' => 'outro', 'active' => true]);
        $manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'team_id' => $team->id]);
        $closer = User::factory()->create(['role' => User::ROLE_CLOSER, 'team_id' => $team->id]);
        $outsider = User::factory()->create(['role' => User::ROLE_CLOSER, 'team_id' => $otherTeam->id]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->lead(['name' => 'Dentro', 'closer_id' => $closer->id]);
        $this->lead(['name' => 'Fora', 'closer_id' => $outsider->id]);
        $this->lead(['name' => 'Do gerente', 'closer_id' => $manager->id]);
        $this->lead(['name' => 'Do admin', 'closer_id' => $admin->id]);

        $this->withToken($this->token($manager))
            ->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Do gerente');

        $this->withToken($this->token($admin))
            ->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Do admin');

        $this->withToken($this->token($outsider))
            ->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Fora');
    }

    public function test_stage_change_is_transactional_and_requires_loss_reason(): void
    {
        $closer = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $lead = $this->lead(['name' => 'Lead Loss', 'closer_id' => $closer->id]);

        $this->withToken($this->token($closer))
            ->patchJson('/api/v1/leads/'.$lead->id.'/stage', ['stage' => 'loss'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->withToken($this->token($closer))
            ->patchJson('/api/v1/leads/'.$lead->id.'/stage', [
                'stage' => 'loss',
                'reason' => 'Sem orçamento',
            ])
            ->assertOk()
            ->assertJsonPath('data.stage', 'loss')
            ->assertJsonPath('data.loss_reason', 'Sem orçamento');

        $this->assertDatabaseHas('lead_stage_histories', [
            'lead_id' => $lead->id,
            'to_stage' => 'loss',
            'reason' => 'Sem orçamento',
        ]);
    }

    public function test_kanban_returns_all_stages_and_reminders_are_scoped(): void
    {
        $closer = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $other = User::factory()->create(['role' => User::ROLE_CLOSER]);
        $lead = $this->lead(['name' => 'Kanban', 'closer_id' => $closer->id]);
        $this->lead(['name' => 'Outro', 'closer_id' => $other->id]);

        Reminder::query()->create([
            'lead_id' => $lead->id,
            'type' => 'follow_up',
            'title' => 'Meu lembrete',
            'due_at' => now()->addDay(),
            'status' => Reminder::STATUS_PENDING,
            'responsible_id' => $closer->id,
        ]);
        Reminder::query()->create([
            'type' => 'follow_up',
            'title' => 'Lembrete alheio',
            'due_at' => now()->addDay(),
            'status' => Reminder::STATUS_PENDING,
            'responsible_id' => $other->id,
        ]);

        $this->withToken($this->token($closer))
            ->getJson('/api/v1/leads/kanban')
            ->assertOk()
            ->assertJsonCount(7, 'data.columns')
            ->assertJsonPath('data.total', 1);

        $this->withToken($this->token($closer))
            ->getJson('/api/v1/reminders')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Meu lembrete');
    }

    private function lead(array $overrides): Lead
    {
        return Lead::query()->create(array_merge([
            'name' => 'Lead',
            'stage' => 'lead_agendado',
            'temperature' => 'warm',
            'last_stage_change_at' => now(),
        ], $overrides));
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
