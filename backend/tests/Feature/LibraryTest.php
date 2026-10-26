<?php

namespace Tests\Feature;

use App\Models\Script;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_scripts_index_returns_a_data_envelope(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Script::query()->create([
            'title' => 'Diagnóstico consultivo',
            'description' => 'Roteiro de descoberta.',
            'sections' => [['title' => 'Abertura', 'content' => 'Contexto e objetivo.']],
            'version' => 1,
            'status' => 'published',
            'updated_by' => $admin->id,
            'published_at' => now(),
        ]);

        $token = app(JwtService::class)->encode([
            'sub' => (string) $admin->id,
            'role' => $admin->role,
            'typ' => 'access',
        ]);

        $this->withToken($token)
            ->getJson('/api/v1/scripts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Diagnóstico consultivo')
            ->assertJsonPath('data.0.sections.0.title', 'Abertura');
    }
}
