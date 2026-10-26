<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly AuditService $audit,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = (string) $request->string('email');
        $throttleKey = $this->throttleKey($email, $request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'message' => 'Muitas tentativas. Aguarde alguns instantes e tente novamente.',
                'code' => 'too_many_attempts',
                'retry_after' => $seconds,
            ], 429)->header('Retry-After', (string) $seconds);
        }

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check((string) $request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Credenciais inválidas.',
                'code' => 'invalid_credentials',
            ], 401);
        }

        if (! $user->active) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'message' => 'Credenciais inválidas.',
                'code' => 'invalid_credentials',
            ], 401);
        }

        RateLimiter::clear($throttleKey);

        try {
            $access = $this->tokens->issueAccessToken($user);
            $session = $this->tokens->createSession($user, $request);
        } catch (\App\Exceptions\JwtConfigurationException) {
            return response()->json([
                'message' => 'Autenticação temporariamente indisponível. Verifique a configuração JWT do servidor.',
                'code' => 'auth_unavailable',
            ], 503);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->record('auth.login', $user, $user, [], [], [], $request);

        return $this->authResponse($user, $access, $session['plain'], 200, $request);
    }

    public function refresh(Request $request): JsonResponse
    {
        if (! $this->originAllowed($request)) {
            return response()->json(['message' => 'Origem não autorizada.', 'code' => 'invalid_origin'], 403);
        }

        $plain = (string) $request->cookie($this->tokens->cookieName());

        if ($plain === '') {
            return response()->json(['message' => 'Sessão não encontrada.', 'code' => 'missing_refresh'], 401);
        }

        try {
            $rotated = $this->tokens->rotate($plain, $request);
        } catch (\App\Exceptions\JwtException $exception) {
            return response()->json([
                'message' => in_array($exception->reason, ['refresh_expired'], true)
                    ? 'Sessão expirada.'
                    : 'Sessão inválida.',
                'code' => $exception->reason,
            ], 401);
        }

        return $this->authResponse($rotated['user'], $rotated['access'], $rotated['refresh'], 200, $request);
    }

    public function logout(Request $request): Response
    {
        if (! $this->originAllowed($request)) {
            return response()->json(['message' => 'Origem não autorizada.', 'code' => 'invalid_origin'], 403);
        }

        $plain = (string) $request->cookie($this->tokens->cookieName());

        if ($plain !== '') {
            $this->tokens->revokeByRefreshToken($plain, 'logout');
        }

        $this->audit->record('auth.logout', $request->user(), null, [], [], [], $request);

        return response()->noContent()->withCookie($this->forgetCookie());
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('team');

        return response()->json([
            'user' => $this->userPayload($user),
        ]);
    }

    private function authResponse(User $user, array $access, string $refresh, int $status, Request $request): JsonResponse
    {
        $user->loadMissing('team');

        return response()->json([
            'access_token' => $access['token'],
            'token_type' => 'Bearer',
            'expires_in' => $access['expires_in'],
            'expires_at' => $access['expires_at'],
            'user' => $this->userPayload($user),
        ], $status)->withCookie($this->refreshCookie($refresh, $request));
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'role_label' => $user->roleLabel(),
            'active' => $user->active,
            'avatar_color' => $user->avatar_color,
            'team_id' => $user->team_id,
            'team' => $user->team ? ['id' => $user->team->id, 'name' => $user->team->name] : null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'permissions' => $user->permissions(),
        ];
    }

    private function refreshCookie(string $value, Request $request): Cookie
    {
        return cookie(
            $this->tokens->cookieName(),
            $value,
            intdiv($this->tokens->cookieLifetimeSeconds(), 60),
            '/api/v1/auth',
            null,
            $this->tokens->cookieSecure() || $request->isSecure(),
            true,
            false,
            $this->tokens->cookieSameSite(),
        );
    }

    private function forgetCookie(): Cookie
    {
        return cookie(
            $this->tokens->cookieName(),
            '',
            -1,
            '/api/v1/auth',
            null,
            $this->tokens->cookieSecure(),
            true,
            false,
            $this->tokens->cookieSameSite(),
        );
    }

    private function throttleKey(string $email, Request $request): string
    {
        return 'login:'.mb_strtolower($email).'|'.$request->ip();
    }

    private function originAllowed(Request $request): bool
    {
        $origin = $request->headers->get('Origin');

        if (! $origin) {
            return true;
        }

        return in_array($origin, config('cors.allowed_origins', []), true);
    }
}
