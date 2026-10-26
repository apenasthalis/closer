<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\ReportRun;
use App\Services\AuditService;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        $filters = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'niche_id' => ['nullable', 'uuid'],
            'closer_id' => ['nullable', 'integer'],
        ]);

        $filters = $this->scopeFilters($request, $filters);
        $user = $request->user();

        return response()->json([
            'data' => [
                'generated_at' => now()->toIso8601String(),
                'dashboard' => $this->dashboard->build($user, $filters),
                'losses' => $this->dashboard->lossAnalysis($user, $filters),
                'limitations' => [
                    'Envio automático de e-mail não está configurado.',
                    'Exportações CSV refletem apenas os dados acessíveis ao perfil autenticado.',
                ],
            ],
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        abort_unless(in_array($type, ['leads', 'calls', 'payments', 'commissions'], true), 404);

        $filters = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'niche_id' => ['nullable', 'uuid'],
            'closer_id' => ['nullable', 'integer'],
        ]);

        $filters = $this->scopeFilters($request, $filters);
        [$from, $to] = $this->period($filters['period'] ?? null);
        $user = $request->user();

        return match ($type) {
            'leads' => $this->exportLeads($user, $filters, $from, $to),
            'calls' => $this->exportCalls($user, $filters, $from, $to),
            'payments' => $this->exportPayments($user, $filters, $from, $to),
            'commissions' => $this->exportCommissions($user, $filters, $from, $to),
        };
    }

    public function weekly(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        $data = $request->validate([
            'week_start' => ['nullable', 'date'],
        ]);

        $start = isset($data['week_start'])
            ? Carbon::parse($data['week_start'])->startOfDay()
            : now()->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $filters = $this->scopeFilters($request, [
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ]);

        $metrics = $this->dashboard->build($request->user(), $filters);
        $losses = $this->dashboard->lossAnalysis($request->user(), $filters);

        $run = ReportRun::query()->create([
            'type' => 'weekly',
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => 'generated',
            'metrics' => [
                'generated_at' => now()->toIso8601String(),
                'period_label' => $start->format('d/m/Y').' a '.$end->format('d/m/Y'),
                'dashboard' => $metrics,
                'losses' => $losses,
            ],
            'recipients' => [],
            'created_by' => $request->user()->getKey(),
        ]);

        $this->audit->record('report.weekly_generated', $request->user(), $run, [], [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ], [], $request);

        return response()->json([
            'data' => $run,
            'message' => 'Relatório semanal gerado. Nenhum e-mail foi enviado porque SMTP não está configurado.',
        ], 201);
    }

    public function runs(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);

        return response()->json([
            'data' => ReportRun::query()->latest()->paginate(25),
        ]);
    }

    private function exportLeads($user, array $filters, Carbon $from, Carbon $to): StreamedResponse
    {
        $query = Lead::query()
            ->visibleTo($user)
            ->with(['niche:id,name', 'closer:id,name'])
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['niche_id'] ?? null, fn ($builder, $niche) => $builder->where('niche_id', $niche))
            ->when($filters['closer_id'] ?? null, fn ($builder, $closer) => $builder->where('closer_id', $closer))
            ->orderBy('created_at');

        return $this->csv('leads-'.$from->format('Y-m').'.csv', [
            'ID', 'Nome', 'Empresa', 'E-mail', 'Telefone', 'Etapa', 'Temperatura', 'Nicho',
            'Closer', 'Ticket estimado', 'Valor negociado', 'Ticket fechado', 'Criado em',
        ], $query->get()->map(fn (Lead $lead) => [
            $lead->getKey(), $lead->name, $lead->company, $lead->email, $lead->phone,
            Lead::STAGE_LABELS[$lead->stage] ?? $lead->stage, $lead->temperature,
            $lead->niche?->name, $lead->closer?->name, (float) $lead->ticket_estimado,
            (float) $lead->valor_negociado, (float) $lead->ticket_fechado,
            $lead->created_at?->format('d/m/Y H:i'),
        ]));
    }

    private function exportCalls($user, array $filters, Carbon $from, Carbon $to): StreamedResponse
    {
        $query = Call::query()
            ->with(['lead:id,name,niche_id', 'closer:id,name,team_id'])
            ->whereBetween('started_at', [$from, $to])
            ->when($filters['closer_id'] ?? null, fn ($builder, $closer) => $builder->where('closer_id', $closer))
            ->orderBy('started_at');

        if (! $user->isAdmin()) {
            if ($user->isManager() && $user->team_id) {
                $query->whereHas('closer', fn ($builder) => $builder->where('team_id', $user->team_id));
            } else {
                $query->where('closer_id', $user->getKey());
            }
        }

        return $this->csv('calls-'.$from->format('Y-m').'.csv', [
            'Data', 'Título', 'Lead', 'Closer', 'Status', 'Duração (s)', 'Score', 'Provider', 'Analisada em',
        ], $query->get()->map(fn (Call $call) => [
            $call->started_at?->format('d/m/Y H:i'), $call->title, $call->lead?->name,
            $call->closer?->name, $call->status, (int) $call->duration_sec,
            $call->playbook_score, $call->ai_provider, $call->analyzed_at?->format('d/m/Y H:i'),
        ]));
    }

    private function exportPayments($user, array $filters, Carbon $from, Carbon $to): StreamedResponse
    {
        $query = Payment::query()
            ->with(['lead:id,name,niche_id,closer_id'])
            ->whereBetween('due_at', [$from->toDateString(), $to->toDateString()])
            ->whereHas('lead', fn ($builder) => $builder->visibleTo($user))
            ->orderBy('due_at');

        return $this->csv('pagamentos-'.$from->format('Y-m').'.csv', [
            'Vencimento', 'Lead', 'Parcela', 'Tipo', 'Valor', 'Status', 'Pago em', 'Método',
        ], $query->get()->map(fn (Payment $payment) => [
            $payment->due_at?->format('d/m/Y'), $payment->lead?->name, $payment->installment_number,
            $payment->kind, (float) $payment->amount, $payment->status,
            $payment->paid_at?->format('d/m/Y H:i'), $payment->method,
        ]));
    }

    private function exportCommissions($user, array $filters, Carbon $from, Carbon $to): StreamedResponse
    {
        $query = Commission::query()
            ->with(['lead:id,name', 'user:id,name'])
            ->where('period', $from->format('Y-m'))
            ->orderBy('created_at');

        if (! $user->isAdmin()) {
            if ($user->isManager() && $user->team_id) {
                $query->whereIn('user_id', \App\Models\User::query()->where('team_id', $user->team_id)->pluck('id'));
            } else {
                $query->where('user_id', $user->getKey());
            }
        }

        return $this->csv('comissoes-'.$from->format('Y-m').'.csv', [
            'Período', 'Lead', 'Usuário', 'Valor da venda', 'Valor recebido', 'Percentual', 'Comissão', 'Status', 'Aprovada em', 'Paga em',
        ], $query->get()->map(fn (Commission $commission) => [
            $commission->period, $commission->lead?->name, $commission->user?->name,
            (float) $commission->sale_amount, (float) $commission->received_amount,
            (float) $commission->percentage, (float) $commission->amount, $commission->status,
            $commission->approved_at?->format('d/m/Y H:i'), $commission->paid_at?->format('d/m/Y H:i'),
        ]));
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, array_map(fn ($value) => $this->safeCsv($value), $row));
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsv(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'".$value;
        }

        return $value;
    }

    private function scopeFilters(Request $request, array $filters): array
    {
        $user = $request->user();

        if ($user->isManager() && $user->team_id) {
            $filters['team_id'] = $user->team_id;
        }

        return $filters;
    }

    private function period(?string $period): array
    {
        $start = $period
            ? Carbon::createFromFormat('Y-m', $period)->startOfMonth()
            : now()->startOfMonth();

        return [$start, $start->copy()->endOfMonth()];
    }
}
