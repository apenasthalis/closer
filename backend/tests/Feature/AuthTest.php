<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_receive_access_token_and_refresh_cookie(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $response = $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', User::ROLE_ADMIN)
            ->assertJsonStructure(['access_token', 'expires_in', 'expires_at', 'user']);
        $response->assertCookie('closer_refresh');

        $token = $response->json('access_token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);

        $this->withToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.leads_total', 0);
    }

    public function test_invalid_credentials_are_rejected_without_user_enumeration(): void
    {
        User::factory()->create(['password' => bcrypt('correct-password')]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong',
        ])->assertUnauthorized()
            ->assertJsonMissingPath('errors');
    }

    public function test_refresh_rotates_cookie_and_rejects_reuse(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_CLOSER, 'active' => true]);

        $login = $this->withHeader('Origin', 'http://localhost:5173')
            ->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertOk();

        $cookie = $login->getCookie('closer_refresh', false);
        $this->assertNotNull($cookie);

        $refresh = $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:5173')
            ->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonStructure(['access_token', 'user']);

        $rotated = $refresh->getCookie('closer_refresh', false);
        $this->assertNotSame($cookie->getValue(), $rotated->getValue());

        $this->withCredentials()
            ->withHeader('Origin', 'http://localhost:5173')
            ->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'refresh_race');
    }

    public function test_inactive_user_cannot_login_or_use_existing_access_token(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertUnauthorized();

        $token = app(\App\Services\Auth\JwtService::class)->encode([
            'sub' => (string) $user->id,
            'role' => $user->role,
            'typ' => 'access',
        ]);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_api_requires_bearer_token_and_rejects_tampering(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);

        $this->getJson('/api/v1/leads')->assertUnauthorized();

        $token = app(\App\Services\Auth\JwtService::class)->encode([
            'sub' => (string) $user->id,
            'role' => $user->role,
            'typ' => 'access',
        ]);

        $parts = explode('.', $token);
        $parts[1] = rtrim(strtr(base64_encode(json_encode([
            'sub' => (string) $user->id,
            'role' => 'admin',
            'typ' => 'access',
            'exp' => time() + 3600,
            'iat' => time(),
            'nbf' => time(),
            'jti' => str_repeat('a', 32),
        ])), '+/', '-_'), '=');

        $this->withToken(implode('.', $parts))->getJson('/api/v1/leads')->assertUnauthorized();
    }
}
