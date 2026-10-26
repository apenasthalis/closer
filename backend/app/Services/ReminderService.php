<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReminderService
{
    private const TYPE_WEIGHT = [
        'cobranca' => 70,
        'parcela' => 70,
        'confirmacao_reuniao' => 60,
        'no_show' => 60,
        'remarcacao' => 55,
        'contrato' => 50,
        'proposta' => 45,
        'negociacao' => 45,
        'follow_up' => 40,
        'tarefa' => 20,
        'ia' => 10,
        'outro' => 10,
    ];

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function create(User $actor, array $data, Request $request): Reminder
    {
        return DB::transaction(function () use ($actor, $data, $request) {
            $lead = ! empty($data['lead_id']) ? Lead::query()->find($data['lead_id']) : null;

            if ($lead && ! $actor->isAdmin() && ! $actor->isManager()
                && $lead->closer_id !== $actor->getKey() && $lead->pre_sales_id !== $actor->getKey()) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Lead não acessível.');
            }

            /** @var Reminder $reminder */
            $reminder = Reminder::query()->create([
                'lead_id' => $lead?->getKey(),
                'type' => $data['type'] ?? 'tarefa',
                'title' => $data['title'],
                'detail' => $data['detail'] ?? null,
                'due_at' => Carbon::parse($data['due_at']),
                'status' => Reminder::STATUS_PENDING,
                'origin' => $data['origin'] ?? 'manual',
                'rule_key' => $data['rule_key'] ?? null,
                'dedupe_key' => $data['dedupe_key'] ?? null,
                'rule_meta' => $data['rule_meta'] ?? null,
                'responsible_id' => $data['responsible_id'] ?? $lead?->closer_id ?? $actor->getKey(),
                'priority' => 0,
            ]);

            $this->recalculatePriority($reminder);
            $this->audit->record('reminder.created', $actor, $reminder, [], $reminder->only(['title', 'due_at', 'type']), [], $request);

            return $reminder->fresh(['lead', 'responsible']);
        });
    }

    public function complete(Reminder $reminder, User $actor, Request $request): Reminder
    {
        if ($reminder->status === Reminder::STATUS_DONE) {
            return $reminder;
        }

        if ($reminder->status === Reminder::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['status' => 'Lembrete cancelado não pode ser concluído.']);
        }

        $reminder->forceFill([
            'status' => Reminder::STATUS_DONE,
            'completed_at' => now(),
        ])->save();

        if ($reminder->lead_id) {
            \App\Models\LeadInteraction::query()->create([
                'lead_id' => $reminder->lead_id,
                'kind' => 'tarefa_concluida',
                'occurred_at' => now(),
                'author' => $actor->name,
                'user_id' => $actor->getKey(),
                'summary' => 'Tarefa concluída: '.$reminder->title,
                'metadata' => ['reminder_id' => $reminder->getKey()],
            ]);
        }

        $this->audit->record('reminder.completed', $actor, $reminder, ['status' => 'pendente'], ['status' => 'concluido'], [], $request);

        return $reminder->fresh();
    }

    public function snooze(Reminder $reminder, User $actor, string $dueAt, ?string $reason, Request $request): Reminder
    {
        if ($reminder->status !== Reminder::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => 'Somente lembretes pendentes podem ser adiados.']);
        }

        $newDue = Carbon::parse($dueAt);

        if ($newDue->lte($reminder->due_at)) {
            throw ValidationException::withMessages(['due_at' => 'A nova data deve ser posterior à data atual do lembrete.']);
        }

        $old = $reminder->only(['due_at', 'rescheduled_count']);

        $reminder->forceFill([
            'snoozed_from' => $reminder->due_at,
            'due_at' => $newDue,
            'rescheduled_count' => $reminder->rescheduled_count + 1,
            'rule_meta' => array_merge($reminder->rule_meta ?? [], [
                'last_snooze_reason' => $reason,
                'last_snoozed_at' => now()->toIso8601String(),
            ]),
        ])->save();

        $this->recalculatePriority($reminder);
        $this->audit->record('reminder.snoozed', $actor, $reminder, $old, $reminder->only(['due_at', 'rescheduled_count']), ['reason' => $reason], $request);

        return $reminder->fresh();
    }

    public function reassign(Reminder $reminder, User $actor, int $responsibleId, Request $request): Reminder
    {
        $responsible = User::query()->where('active', true)->findOrFail($responsibleId);
        $old = $reminder->responsible_id;
        $reminder->forceFill(['responsible_id' => $responsible->getKey()])->save();

        $this->audit->record('reminder.reassigned', $actor, $reminder, ['responsible_id' => $old], ['responsible_id' => $responsibleId], [], $request);

        return $reminder->fresh(['responsible']);
    }

    public function cancel(Reminder $reminder, User $actor, Request $request): Reminder
    {
        $oldStatus = $reminder->status;

        $reminder->forceFill([
            'status' => Reminder::STATUS_CANCELLED,
            'rule_meta' => array_merge($reminder->rule_meta ?? [], ['cancelled_at' => now()->toIso8601String()]),
        ])->save();

        $this->audit->record('reminder.cancelled', $actor, $reminder, ['status' => $oldStatus], ['status' => 'cancelado'], [], $request);

        return $reminder->fresh();
    }

    public function recalculatePriority(Reminder $reminder): int
    {
        $priority = self::TYPE_WEIGHT[$reminder->type] ?? 10;
        $due = $reminder->due_at;
        $now = now();

        if ($due && $due->isPast()) {
            $priority += min(50, max(0, (int) $due->diffInDays($now)) * 10);
            $priority += 10;
        } elseif ($due && $due->isToday()) {
            $priority += 20;
        } elseif ($due && $due->lte($now->copy()->addDay())) {
            $priority += 10;
        }

        $lead = $reminder->relationLoaded('lead') ? $reminder->lead : $reminder->lead()->first();

        if ($lead) {
            $priority += match ($lead->temperature) {
                'hot' => 25,
                'warm' => 10,
                default => 0,
            };

            $value = (float) ($lead->ticket_estimado ?? $lead->valor_negociado ?? 0);
            $priority += match (true) {
                $value >= 10000 => 30,
                $value >= 5000 => 20,
                $value >= 2000 => 10,
                default => 0,
            };

            if ($lead->next_action_at && $lead->next_action_at->isToday()) {
                $priority += 15;
            }
        }

        $priority = max(0, min(200, $priority));
        $reminder->forceFill(['priority' => $priority])->save();

        return $priority;
    }

    public function syncForLead(Lead $lead): void
    {
        // Reservado para regras específicas do lead. A automação principal é idempotente
        // e executada por ReminderAutomationService.
    }
}
