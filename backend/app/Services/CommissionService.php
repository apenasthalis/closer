<?php

namespace App\Services;

use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function ruleFor(Lead $lead): ?CommissionRule
    {
        $rules = CommissionRule::query()
            ->where('active', true)
            ->where(function ($query) use ($lead) {
                $query->where('scope', 'global')
                    ->orWhere(function ($inner) use ($lead) {
                        $inner->where('scope', 'user')->where('user_id', $lead->closer_id);
                    })
                    ->orWhere(function ($inner) use ($lead) {
                        $inner->where('scope', 'niche')->where('niche_id', $lead->niche_id);
                    })
                    ->orWhere(function ($inner) use ($lead) {
                        $inner->where('scope', 'team')->whereIn(
                            'team_id',
                            User::query()->whereKey($lead->closer_id)->pluck('team_id')->filter()
                        );
                    });
            })
            ->orderByDesc('priority')
            ->orderByRaw("case scope when 'user' then 4 when 'team' then 3 when 'niche' then 2 else 1 end desc")
            ->get();

        return $rules->first();
    }

    public function calculateForLead(Lead $lead, string $period): ?Commission
    {
        if ($lead->stage !== 'fechado' || ! $lead->closer_id) {
            return null;
        }

        return DB::transaction(function () use ($lead, $period) {
            $rule = $this->ruleFor($lead);

            if (! $rule || ($rule->percentage === null && $rule->fixed_amount === null)) {
                return null;
            }

            $existing = Commission::query()
                ->where('lead_id', $lead->getKey())
                ->where('user_id', $lead->closer_id)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            if ($existing && in_array($existing->status, ['approved', 'paid'], true)) {
                return $existing;
            }

            $received = (float) Payment::query()
                ->where('lead_id', $lead->getKey())
                ->where('status', 'paid')
                ->sum('amount');

            $saleAmount = (float) ($lead->ticket_fechado ?? 0);
            $basisAmount = $rule->basis === 'received' ? $received : $saleAmount;
            $amount = ($rule->percentage !== null ? $basisAmount * ((float) $rule->percentage / 100) : 0)
                + (float) ($rule->fixed_amount ?? 0);

            $snapshot = [
                'rule_id' => $rule->getKey(),
                'name' => $rule->name,
                'scope' => $rule->scope,
                'percentage' => $rule->percentage,
                'fixed_amount' => $rule->fixed_amount,
                'basis' => $rule->basis,
                'captured_at' => now()->toIso8601String(),
            ];

            $commission = Commission::query()->updateOrCreate(
                [
                    'lead_id' => $lead->getKey(),
                    'user_id' => $lead->closer_id,
                    'period' => $period,
                ],
                [
                    'rule_id' => $rule->getKey(),
                    'rule_snapshot' => $snapshot,
                    'sale_amount' => $saleAmount,
                    'received_amount' => $received,
                    'percentage' => $rule->percentage,
                    'fixed_amount' => $rule->fixed_amount,
                    'amount' => round($amount, 2),
                    'status' => $existing?->status === 'reversed' ? 'estimated' : ($existing?->status ?? 'calculated'),
                    'calculated_at' => now(),
                ]
            );

            $this->audit->record('commission.calculated', null, $commission, [], [
                'amount' => (float) $commission->amount,
                'rule_snapshot' => $snapshot,
            ]);

            return $commission->fresh(['lead:id,name', 'user:id,name']);
        });
    }

    public function calculatePeriod(User $actor, string $period): array
    {
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $leads = Lead::query()
            ->where('stage', 'fechado')
            ->whereBetween('last_stage_change_at', [$start, $end])
            ->whereNotNull('closer_id')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            $commission = $this->calculateForLead($lead, $period);

            if ($commission) {
                $created++;
            } else {
                $skipped++;
            }
        }

        $this->audit->record('commission.period_calculated', $actor, null, [], [
            'period' => $period,
            'created' => $created,
            'skipped' => $skipped,
        ]);

        return ['period' => $period, 'calculated' => $created, 'skipped' => $skipped];
    }

    public function approve(Commission $commission, User $actor): Commission
    {
        if ($actor->getKey() === $commission->user_id) {
            throw ValidationException::withMessages(['commission' => 'Um usuário não pode aprovar a própria comissão.']);
        }

        if (! in_array($commission->status, ['estimated', 'calculated'], true)) {
            throw ValidationException::withMessages(['commission' => 'Somente comissões estimadas ou calculadas podem ser aprovadas.']);
        }

        $old = $commission->only(['status', 'approved_at', 'approved_by']);

        $commission->forceFill([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $actor->getKey(),
        ])->save();

        $this->audit->record('commission.approved', $actor, $commission, $old, $commission->only(['status', 'approved_at', 'approved_by']));

        return $commission->fresh(['lead:id,name', 'user:id,name']);
    }

    public function markPaid(Commission $commission, User $actor): Commission
    {
        if ($commission->status !== 'approved') {
            throw ValidationException::withMessages(['commission' => 'Somente comissões aprovadas podem ser pagas.']);
        }

        $old = $commission->only(['status', 'paid_at']);
        $commission->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $this->audit->record('commission.paid', $actor, $commission, $old, $commission->only(['status', 'paid_at']));

        return $commission->fresh(['lead:id,name', 'user:id,name']);
    }

    public function reverse(Commission $commission, User $actor, string $reason): Commission
    {
        if (in_array($commission->status, ['reversed'], true)) {
            return $commission;
        }

        $old = $commission->only(['status', 'reversed_at', 'notes']);

        $commission->forceFill([
            'status' => 'reversed',
            'reversed_at' => now(),
            'notes' => trim(($commission->notes ? $commission->notes."\n" : '').'Estorno: '.$reason),
        ])->save();

        $this->audit->record('commission.reversed', $actor, $commission, $old, $commission->only(['status', 'reversed_at']), ['reason' => $reason]);

        return $commission->fresh(['lead:id,name', 'user:id,name']);
    }
}
