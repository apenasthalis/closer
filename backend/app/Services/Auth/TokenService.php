<?php

namespace App\Services\Auth;

use App\Exceptions\JwtException;
use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokenService
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function issueAccessToken(User $user): array
    {
        $ttl = (int) config('jwt.access_ttl', 900);

        $token = $this->jwt->encode([
            'sub' => (string) $user->getKey(),
            'role' => $user->role,
            'typ' => 'access',
        ], $ttl);

        return [
            'token' => $token,
            'expires_in' => $ttl,
            'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
        ];
    }

    /**
     * @return array{plain:string, session:AuthSession}
     */
    public function createSession(User $user, Request $request, ?AuthSession $replaced = null, ?string $reason = null): array
    {
        $plain = $this->randomToken();
        $days = (int) config('jwt.refresh_ttl_days', 14);

        $session = AuthSession::create([
            'user_id' => $user->getKey(),
            'refresh_token_hash' => $this->hash($plain),
            'expires_at' => now()->addDays($days),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'last_used_at' => now(),
        ]);

        if ($replaced) {
            $replaced->forceFill([
                'revoked_at' => now(),
                'revoked_reason' => $reason ?? 'rotated',
                'replaced_by_id' => $session->getKey(),
            ])->save();
        }

        return ['plain' => $plain, 'session' => $session];
    }

    /**
     * @return array{user:User, access:array, refresh:string, session:AuthSession}
     */
    public function rotate(string $plainRefresh, Request $request): array
    {
        $hash = $this->hash($plainRefresh);

        return DB::transaction(function () use ($hash, $request): array {
            /** @var AuthSession|null $session */
            $session = AuthSession::query()
                ->where('refresh_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw new JwtException('Sessão inválida.', 'refresh_invalid');
            }

            if ($session->revoked_at !== null) {
                $reuseWindow = 30;

                if ($session->revoked_at->gt(now()->subSeconds($reuseWindow))) {
                    throw new JwtException('Sessão já renovada. Tente novamente.', 'refresh_race');
                }

                $this->revokeAllForUser($session->user_id, 'refresh_reuse_detected');

                throw new JwtException('Sessão inválida.', 'refresh_reuse');
            }

            if (! $session->expires_at->isFuture()) {
                $session->forceFill(['revoked_at' => now(), 'revoked_reason' => 'expired'])->save();

                throw new JwtException('Sessão expirada.', 'refresh_expired');
            }

            /** @var User|null $user */
            $user = $session->user()->first();

            if (! $user || ! $user->active) {
                $session->forceFill(['revoked_at' => now(), 'revoked_reason' => 'user_inactive'])->save();

                throw new JwtException('Sessão inválida.', 'user_inactive');
            }

            $session->forceFill(['last_used_at' => now()])->save();

            $created = $this->createSession($user, $request, $session, 'rotated');

            return [
                'user' => $user,
                'access' => $this->issueAccessToken($user),
                'refresh' => $created['plain'],
                'session' => $created['session'],
            ];
        });
    }

    public function revokeByRefreshToken(string $plainRefresh, string $reason = 'logout'): void
    {
        $session = AuthSession::query()
            ->where('refresh_token_hash', $this->hash($plainRefresh))
            ->whereNull('revoked_at')
            ->first();

        if ($session) {
            $session->forceFill([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
            ])->save();
        }
    }

    public function revokeAllForUser(int $userId, string $reason = 'revoked'): int
    {
        return AuthSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'revoked_reason' => $reason,
                'updated_at' => now(),
            ]);
    }

    public function pruneExpired(): int
    {
        $cutoff = now()->subDays(30);

        return AuthSession::query()
            ->where(function ($query) use ($cutoff) {
                $query->where('expires_at', '<', now())
                    ->orWhere(function ($inner) use ($cutoff) {
                        $inner->whereNotNull('revoked_at')->where('revoked_at', '<', $cutoff);
                    });
            })
            ->delete();
    }

    public function cookieName(): string
    {
        return (string) config('jwt.cookie_name', 'closer_refresh');
    }

    public function cookieLifetimeSeconds(): int
    {
        return (int) config('jwt.refresh_ttl_days', 14) * 86400;
    }

    public function cookieSecure(): bool
    {
        return (bool) config('jwt.cookie_secure', false) || app()->environment('production');
    }

    public function cookieSameSite(): string
    {
        return (string) config('jwt.cookie_same_site', 'lax');
    }

    private function randomToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
