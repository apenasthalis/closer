<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntegrationSetting;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class SettingsController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManageSettings(), 403);

        return response()->json([
            'data' => [
                'system' => [
                    'app_name' => config('app.name'),
                    'environment' => app()->environment(),
                    'timezone' => config('app.timezone'),
                    'queue' => config('queue.default'),
                    'cache' => config('cache.default'),
                    'mailer' => config('mail.default'),
                ],
                'integrations' => $this->integrations(),
            ],
        ]);
    }

    public function updateIntegration(Request $request, string $provider): JsonResponse
    {
        abort_unless($request->user()->canManageSettings(), 403);
        $definitions = $this->definitions();
        abort_unless(isset($definitions[$provider]), 404, 'Integração desconhecida.');

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'config' => ['nullable', 'array'],
        ]);

        $config = $data['config'] ?? [];
        $secretKeys = collect(Arr::dot($config))
            ->keys()
            ->filter(fn ($key) => preg_match('/(secret|token|password|api_key|private_key)/i', (string) $key))
            ->values();

        if ($secretKeys->isNotEmpty()) {
            return response()->json([
                'message' => 'Credenciais não podem ser salvas pelo painel. Configure-as apenas em variáveis de ambiente do servidor.',
                'invalid_keys' => $secretKeys,
            ], 422);
        }

        $envReady = $this->envReady($definitions[$provider]['required']);

        $setting = IntegrationSetting::query()->updateOrCreate(
            ['provider' => $provider],
            [
                'enabled' => (bool) $data['enabled'],
                'status' => ! $data['enabled'] ? 'disabled' : ($envReady ? 'configured' : 'not_configured'),
                'config' => $config,
                'last_checked_at' => now(),
                'updated_by' => $request->user()->getKey(),
            ]
        );

        $this->audit->record('integration.updated', $request->user(), null, [], [
            'provider' => $provider,
            'enabled' => (bool) $data['enabled'],
            'status' => $setting->status,
        ], [], $request);

        return response()->json([
            'data' => $this->serializeIntegration($provider, $definitions[$provider], $setting),
            'message' => 'Preferências da integração atualizadas.',
        ]);
    }

    public function testIntegration(Request $request, string $provider): JsonResponse
    {
        abort_unless($request->user()->canManageSettings(), 403);
        $definitions = $this->definitions();
        abort_unless(isset($definitions[$provider]), 404);

        $envReady = $this->envReady($definitions[$provider]['required']);
        $setting = IntegrationSetting::query()->where('provider', $provider)->first();

        if (! $envReady || ! $setting?->enabled) {
            return response()->json([
                'status' => 'not_configured',
                'message' => 'Integração não configurada. Defina as variáveis de ambiente e habilite a integração.',
            ], 422);
        }

        return response()->json([
            'status' => 'not_tested',
            'message' => 'As credenciais estão presentes, mas este teste externo não foi implementado nesta versão. Nenhuma chamada simulada foi feita.',
        ]);
    }

    private function integrations(): array
    {
        $definitions = $this->definitions();
        $settings = IntegrationSetting::query()->get()->keyBy('provider');

        return collect($definitions)->map(fn ($definition, $provider) => $this->serializeIntegration(
            $provider,
            $definition,
            $settings->get($provider)
        ))->values()->all();
    }

    private function serializeIntegration(string $provider, array $definition, ?IntegrationSetting $setting): array
    {
        $envReady = $this->envReady($definition['required']);

        return [
            'provider' => $provider,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'enabled' => (bool) ($setting?->enabled ?? false),
            'status' => $setting?->status ?? 'not_configured',
            'environment_configured' => $envReady,
            'required_environment' => $definition['required'],
            'config' => $setting?->config ?? [],
            'last_checked_at' => $setting?->last_checked_at?->toIso8601String(),
            'capability' => 'A integração está pronta para exibição de status; envio/sincronização real depende de implementação do conector.',
        ];
    }

    private function definitions(): array
    {
        return [
            'google_calendar' => [
                'label' => 'Google Calendar',
                'description' => 'Sincronização de reuniões e links de calendário.',
                'required' => ['services.google.client_id', 'services.google.client_secret'],
            ],
            'whatsapp_cloud' => [
                'label' => 'WhatsApp Cloud API',
                'description' => 'Envio de mensagens e modelos aprovados.',
                'required' => ['services.whatsapp.token', 'services.whatsapp.phone_number_id'],
            ],
            'ai' => [
                'label' => 'IA externa',
                'description' => 'Provedor externo de análise de calls. A análise local heurística já está disponível.',
                'required' => ['services.ai.api_key'],
            ],
            'smtp' => [
                'label' => 'E-mail SMTP',
                'description' => 'Disparo de relatórios e notificações por e-mail.',
                'required' => ['mail.mailers.smtp.host', 'mail.mailers.smtp.username'],
            ],
        ];
    }

    private function envReady(array $keys): bool
    {
        foreach ($keys as $key) {
            $value = config($key);

            if (! is_string($value) || trim($value) === '') {
                return false;
            }

            if (preg_match('/(placeholder|changeme|your[-_ ]|example|todo)/i', $value)) {
                return false;
            }
        }

        return true;
    }
}
