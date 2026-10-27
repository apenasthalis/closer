<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\LeadStageHistory;
use App\Models\Niche;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Regras de cálculo:
     * - Perdido/desqualificado e fechado usam last_stage_change_at dentro do período.
     * - Receita contratada soma ticket_fechado de leads fechados no período.
     * - Receita recebida soma pagamentos pagos no período; pendente soma pagamentos pendentes.
     * - Taxa de comparecimento = concluídas / (concluídas + no-show), sem contar agendadas/canceladas.
     * - Conversão = fechados no período / leads criados no período.
     * - Cada lead aparece uma vez em cada métrica; pagamentos e calls são vinculados por lead.
     *
     * @return array<string, mixed>
     */
    public function build(User $user, array $filters = []): array
    {
        [$from, $to] = $this->period($filters);

        $leads = Lead::query()
            ->visibleTo($user)
            ->with(['niche:id,name,color', 'closer:id,name,team_id', 'preSales:id,name'])
            ->when($filters['closer_id'] ?? null, fn (Builder $query, $id) => $query->where('closer_id', $id))
            ->when($filters['pre_sales_id'] ?? null, fn (Builder $query, $id) => $query->where('pre_sales_id', $id))
            ->when($filters['niche_id'] ?? null, fn (Builder $query, $id) => $query->where('niche_id', $id))
            ->when($filters['origin'] ?? null, fn (Builder $query, $origin) => $query->where('origin', $origin))
            ->when($filters['stage'] ?? null, fn (Builder $query, $stage) => $query->where('stage', $stage))
            ->when($filters['temperature'] ?? null, fn (Builder $query, $temp) => $query->where('temperature', $temp))
            ->when($filters['team_id'] ?? null, function (Builder $query, $teamId) {
                $query->where(function (Builder $inner) use ($teamId) {
                    $inner->whereHas('closer', fn (Builder $closer) => $closer->where('team_id', $teamId))
                        ->orWhereHas('preSales', fn (Builder $sdr) => $sdr->where('team_id', $teamId));
                });
            })
            ->get();

        $leadIds = $leads->pluck('id')->all();

        $today = now()->toDateString();
        $meetingsToday = $leads->filter(fn (Lead $lead) => $lead->meeting_at && $lead->meeting_at->format('Y-m-d') === $today);
        $meetingsTodayClosed = $meetingsToday->where('stage', 'fechado')->count();
        $meetingsTodayLoss = $meetingsToday->where('stage', 'loss')->count();
        $createdInPeriod = $leads->filter(fn (Lead $lead) => $this->between($lead->created_at, $from, $to));
        $closedInPeriod = $leads->filter(fn (Lead $lead) => $lead->stage === 'fechado' && $this->between($lead->last_stage_change_at ?? $lead->updated_at, $from, $to));
        $lostInPeriod = $leads->filter(fn (Lead $lead) => $lead->stage === 'loss' && $this->between($lead->last_stage_change_at ?? $lead->updated_at, $from, $to));
        $disqualifiedInPeriod = $leads->filter(fn (Lead $lead) => $lead->stage === 'mal_qualificado' && $this->between($lead->last_stage_change_at ?? $lead->updated_at, $from, $to));

        $calls = Call::query()
            ->whereIn('lead_id', $leadIds)
            ->whereBetween('started_at', [$from, $to])
            ->with('closer:id,name')
            ->get();

        $payments = Payment::query()
            ->whereIn('lead_id', $leadIds)
            ->get();

        $commissions = Commission::query()
            ->whereIn('lead_id', $leadIds)
            ->where('status', '!=', 'reversed')
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $stageHistory = LeadStageHistory::query()
            ->whereIn('lead_id', $leadIds)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $leadsByStage = [];
        foreach (Lead::STAGES as $stage) {
            $leadsByStage[$stage] = $leads->where('stage', $stage)->count();
        }

        $stageTotals = [];
        foreach (Lead::STAGES as $stage) {
            $stageTotals[$stage] = [
                'count' => $leads->where('stage', $stage)->count(),
                'value' => (float) $leads->where('stage', $stage)->sum(fn (Lead $lead) => (float) ($lead->valor_negociado ?? $lead->ticket_estimado ?? 0)),
            ];
        }

        $closedCount = $closedInPeriod->count();
        $createdCount = $createdInPeriod->count();
        $completedCalls = $calls->where('status', 'completed');
        $noShowCalls = $calls->where('status', 'no_show');
        $attendanceBase = $completedCalls->count() + $noShowCalls->count();

        $contracted = (float) $closedInPeriod->sum(fn (Lead $lead) => (float) ($lead->ticket_fechado ?? 0));
        $received = (float) $payments->where('status', 'paid')
            ->filter(fn (Payment $payment) => $this->between($payment->paid_at, $from, $to))
            ->sum(fn (Payment $payment) => (float) $payment->amount);
        $pending = (float) $payments->where('status', 'pending')->sum(fn (Payment $payment) => (float) $payment->amount);
        $overdue = (float) $payments->where('status', 'pending')
            ->filter(fn (Payment $payment) => $payment->due_at->isPast())
            ->sum(fn (Payment $payment) => (float) $payment->amount);

        $activeLeads = $leads->whereNotIn('stage', ['fechado', 'loss', 'mal_qualificado']);
        $forecast = (float) $activeLeads->sum(fn (Lead $lead) => (float) ($lead->receita_prevista ?? $lead->ticket_estimado ?? 0));

        $cycleDays = $closedInPeriod->map(function (Lead $lead) {
            return $lead->created_at && $lead->last_stage_change_at
                ? $lead->created_at->diffInDays($lead->last_stage_change_at)
                : null;
        })->filter(fn ($days) => $days !== null);

        $meetingsByDay = $calls
            ->groupBy(fn (Call $call) => $call->started_at->toDateString())
            ->map(fn (Collection $items) => [
                'date' => $items->first()->started_at->toDateString(),
                'completed' => $items->where('status', 'completed')->count(),
                'scheduled' => $items->where('status', 'scheduled')->count(),
                'no_show' => $items->where('status', 'no_show')->count(),
                'cancelled' => $items->where('status', 'cancelled')->count(),
            ])
            ->sortBy('date')
            ->values();

        $byNiche = Niche::query()
            ->whereIn('id', $leads->pluck('niche_id')->filter()->unique()->values())
            ->get()
            ->map(function (Niche $niche) use ($createdInPeriod, $closedInPeriod) {
                $nicheCreated = $createdInPeriod->where('niche_id', $niche->id);
                $nicheClosed = $closedInPeriod->where('niche_id', $niche->id);

                return [
                    'id' => $niche->id,
                    'name' => $niche->name,
                    'color' => $niche->color,
                    'leads' => $nicheCreated->count(),
                    'closed' => $nicheClosed->count(),
                    'revenue' => (float) $nicheClosed->sum(fn (Lead $lead) => (float) ($lead->ticket_fechado ?? 0)),
                    'conversion' => $nicheCreated->count() > 0
                        ? round($nicheClosed->count() / $nicheCreated->count() * 100, 1)
                        : 0.0,
                ];
            })
            ->values();

        $closerIds = $leads->pluck('closer_id')->filter()->unique()->values();
        $users = User::query()->whereIn('id', $closerIds)->get()->keyBy('id');

        $ranking = $leads->whereNotNull('closer_id')
            ->groupBy('closer_id')
            ->map(function (Collection $items, $closerId) use ($users, $calls, $closedInPeriod, $commissions, $createdInPeriod) {
                $user = $users->get((int) $closerId);
                $userCalls = $calls->where('closer_id', (int) $closerId);
                $completed = $userCalls->where('status', 'completed')->count();
                $noShow = $userCalls->where('status', 'no_show')->count();
                $userClosed = $closedInPeriod->where('closer_id', (int) $closerId);
                $userCreated = $createdInPeriod->where('closer_id', (int) $closerId);
                $cycle = $userClosed->map(fn (Lead $lead) => $lead->created_at && $lead->last_stage_change_at
                    ? $lead->created_at->diffInDays($lead->last_stage_change_at)
                    : null)->filter(fn ($days) => $days !== null);

                return [
                    'id' => (int) $closerId,
                    'name' => $user?->name ?? 'Sem responsável',
                    'leads' => $items->count(),
                    'leads_created' => $userCreated->count(),
                    'meetings_completed' => $completed,
                    'attendance_rate' => ($completed + $noShow) > 0 ? round($completed / ($completed + $noShow) * 100, 1) : null,
                    'closed' => $userClosed->count(),
                    'conversion' => $userCreated->count() > 0 ? round($userClosed->count() / $userCreated->count() * 100, 1) : 0.0,
                    'revenue' => (float) $userClosed->sum(fn (Lead $lead) => (float) ($lead->ticket_fechado ?? 0)),
                    'average_ticket' => $userClosed->count() > 0
                        ? round((float) $userClosed->sum(fn (Lead $lead) => (float) ($lead->ticket_fechado ?? 0)) / $userClosed->count(), 2)
                        : 0.0,
                    'cycle_days' => $cycle->count() > 0 ? round($cycle->avg(), 1) : null,
                    'commission' => (float) $commissions->where('user_id', (int) $closerId)->sum(fn (Commission $commission) => (float) $commission->amount),
                ];
            })
            ->sortByDesc('revenue')
            ->values();

        $stageReached = [];
        foreach (Lead::STAGES as $stage) {
            $stageReached[$stage] = $stageHistory->where('to_stage', $stage)->pluck('lead_id')->unique()->count();
        }

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'rules' => [
                'contracted' => 'Soma de ticket_fechado dos leads fechados no período.',
                'received' => 'Soma de pagamentos com status paid e paid_at no período.',
                'pending' => 'Soma de pagamentos com status pending.',
                'conversion' => 'Leads fechados no período ÷ leads criados no período.',
                'attendance' => 'Calls concluídas ÷ (concluídas + no-show) no período.',
                'forecast' => 'Soma de receita_prevista (ou ticket_estimado) dos leads ativos.',
            ],
            'leads_total' => $leads->count(),
            'leads_created' => $createdCount,
            'leads_by_stage' => $leadsByStage,
            'stage_totals' => $stageTotals,
            'closed' => $closedCount,
            'lost' => $lostInPeriod->count(),
            'disqualified' => $disqualifiedInPeriod->count(),
            'conversion_rate' => $createdCount > 0 ? round($closedCount / $createdCount * 100, 1) : 0.0,
            'disqualification_rate' => $createdCount > 0 ? round($disqualifiedInPeriod->count() / $createdCount * 100, 1) : 0.0,
            'ticket_medio' => $closedCount > 0 ? round($contracted / $closedCount, 2) : 0.0,
            'cycle_days' => $cycleDays->count() > 0 ? round($cycleDays->avg(), 1) : null,
            'revenue' => [
                'forecast' => $forecast,
                'contracted' => $contracted,
                'received' => $received,
                'pending' => $pending,
                'overdue' => $overdue,
                'debtor_balance' => (float) $leads->sum(fn (Lead $lead) => (float) ($lead->saldo_devedor ?? 0)),
                'commissions' => (float) $commissions->sum(fn (Commission $commission) => (float) $commission->amount),
            ],
            'meetings' => [
                'total' => $calls->count(),
                'completed' => $completedCalls->count(),
                'scheduled' => $calls->where('status', 'scheduled')->count(),
                'no_show' => $noShowCalls->count(),
                'cancelled' => $calls->where('status', 'cancelled')->count(),
                'attendance_rate' => $attendanceBase > 0 ? round($completedCalls->count() / $attendanceBase * 100, 1) : null,
                'by_day' => $meetingsByDay,
            ],
            'by_niche' => $byNiche,
            'ranking' => $ranking,
            'conversion_by_stage' => $stageReached,
            'commissions_available' => $user->canViewFinancials() || $user->isCloser(),
            'meetings_today' => [
                'total' => $meetingsToday->count(),
                'closed' => $meetingsTodayClosed,
                'loss' => $meetingsTodayLoss,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lossAnalysis(User $user, array $filters = []): array
    {
        [$from, $to] = $this->period($filters);

        $leads = Lead::query()
            ->visibleTo($user)
            ->with(['niche:id,name', 'closer:id,name'])
            ->whereIn('stage', ['loss', 'mal_qualificado'])
            ->whereBetween('last_stage_change_at', [$from, $to])
            ->get();

        $loss = $leads->where('stage', 'loss');
        $disqualified = $leads->where('stage', 'mal_qualificado');

        $reasonCounts = function (Collection $items, string $field): array {
            return $items->groupBy(fn (Lead $lead) => trim((string) ($lead->{$field} ?: 'Não informado')))
                ->map(fn (Collection $group, $reason) => [
                    'reason' => $reason,
                    'count' => $group->count(),
                    'value' => (float) $group->sum(fn (Lead $lead) => (float) ($lead->ticket_estimado ?? 0)),
                ])
                ->sortByDesc('count')
                ->values()
                ->all();
        };

        $distribution = function (Collection $items, string $field): array {
            return $items->groupBy(fn (Lead $lead) => $lead->{$field}?->name ?? 'Não informado')
                ->map(fn (Collection $group, $name) => ['name' => $name, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->all();
        };

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'loss' => $loss->count(),
                'mal_qualificado' => $disqualified->count(),
                'estimated_value' => (float) $leads->sum(fn (Lead $lead) => (float) ($lead->ticket_estimado ?? 0)),
                'average_days_to_loss' => round($leads->map(fn (Lead $lead) => $lead->created_at && $lead->last_stage_change_at
                    ? $lead->created_at->diffInDays($lead->last_stage_change_at)
                    : 0)->avg() ?? 0, 1),
            ],
            'loss_reasons' => $reasonCounts($loss, 'loss_reason'),
            'disqualify_reasons' => $reasonCounts($disqualified, 'disqualify_reason'),
            'by_niche' => $distribution($leads, 'niche'),
            'by_closer' => $distribution($leads, 'closer'),
            'by_origin' => $leads->groupBy(fn (Lead $lead) => $lead->origin ?: 'Não informado')
                ->map(fn (Collection $group, $origin) => ['name' => $origin, 'count' => $group->count()])
                ->sortByDesc('count')
                ->values()
                ->all(),
            'caveat' => 'Distribuições descritivas. Não estabelecem causalidade.',
        ];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function period(array $filters): array
    {
        $from = ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : now()->startOfMonth();
        $to = ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function between(?Carbon $date, Carbon $from, Carbon $to): bool
    {
        return $date !== null && $date->betweenIncluded($from, $to);
    }
}
