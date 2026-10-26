<?php

namespace App\Http\Middleware;

use App\Exceptions\JwtConfigurationException;
use App\Exceptions\JwtException;
use App\Models\User;
use App\Services\Auth\JwtService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private readonly JwtService $jwt)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return $this->unauthorized('Autenticação necessária.', 'missing_token');
        }

        try {
            $claims = $this->jwt->decode($matches[1]);
        } catch (JwtConfigurationException) {
            return response()->json([
                'message' => 'Autenticação temporariamente indisponível.',
                'code' => 'auth_unavailable',
            ], 503);
        } catch (JwtException $exception) {
            $message = match ($exception->reason) {
                'expired' => 'Sessão expirada.',
                'not_yet_valid' => 'Token ainda não é válido.',
                default => 'Autenticação inválida.',
            };

            return $this->unauthorized($message, $exception->reason);
        }

        if (($claims['typ'] ?? null) !== 'access') {
            return $this->unauthorized('Tipo de token inválido.', 'invalid_token_type');
        }

        $user = User::query()->with('team')->find((int) $claims['sub']);

        if (! $user) {
            return $this->unauthorized('Autenticação inválida.', 'user_not_found');
        }

        if (! $user->active) {
            return $this->unauthorized('Conta desativada.', 'user_inactive');
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('jwt_claims', $claims);

        return $next($request);
    }

    private function unauthorized(string $message, string $code): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'code' => $code,
        ], 401);
    }
}
