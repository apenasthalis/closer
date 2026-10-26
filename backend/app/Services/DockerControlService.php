<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class DockerControlService
{
    private const ALLOWED = ['closer-php-1', 'closer-nginx-1', 'closer-frontend-1', 'postgres-closer'];

    public function status(): array
    {
        $url = config('services.docker_control.url');
        $token = config('services.docker_control.token');

        if (! $url || ! $token) {
            return [
                'available' => false,
                'reason' => 'Agente de controle Docker não configurado. Use ./dev.sh up no servidor.',
                'services' => [],
            ];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(4)
                ->acceptJson()
                ->get(rtrim($url, '/').'/status');

            if (! $response->successful()) {
                return [
                    'available' => false,
                    'reason' => 'Agente Docker respondeu com HTTP '.$response->status().'.',
                    'services' => [],
                ];
            }

            return [
                'available' => true,
                'reason' => null,
                'services' => $response->json('services', []),
                'agent' => $response->json('agent'),
            ];
        } catch (Throwable) {
            return [
                'available' => false,
                'reason' => 'Agente Docker inacessível. Use ./dev.sh up no servidor.',
                'services' => [],
            ];
        }
    }

    public function start(): array
    {
        $url = config('services.docker_control.url');
        $token = config('services.docker_control.token');

        if (! $url || ! $token) {
            return [
                'ok' => false,
                'error' => 'Agente de controle Docker não configurado.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->acceptJson()
                ->post(rtrim($url, '/').'/start', ['services' => self::ALLOWED]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'error' => 'Agente Docker respondeu com HTTP '.$response->status().'.',
                ];
            }

            return ['ok' => true, 'result' => $response->json()];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'Agente Docker inacessível.'];
        }
    }
}
