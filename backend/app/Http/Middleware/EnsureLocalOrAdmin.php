<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalOrAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowedEnvironment = app()->environment(['local', 'testing'])
            || filter_var(env('ALLOW_DOCKER_CONTROL', false), FILTER_VALIDATE_BOOL);

        if (! $user || ! $user->isAdmin() || ! $allowedEnvironment) {
            return response()->json([
                'message' => 'Controle do ambiente restrito a administradores no ambiente local.',
                'code' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
