<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadInteraction;
use App\Models\LeadStageHistory;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ReminderService $reminders,
    ) {
    }

    public function create(User $actor, array $data, Request $request): Lead
    {
        return DB::transaction(function () use ($actor, $data, $request) {
            $stage = $data['stage'] ?? 'lead_agendado';
            $closerId = $data['closer_id'] ?? ($actor->isCloser() ? $actor->getKey() : null);
            $preSalesId = $data['pre_sales_id'] ?? ($actor->isSdr() ? $actor->getKey() : null);

            $position = (int) Lead::query()->where('stage', $stage)->max('position') + 1;

            /** @var Lead $lead */
            $lead = Lead::query()->create([
                'name' => $data['name'],
                'company' => $data['company'] ?? null,
                'phone' => $data['phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'email' => $data['email'] ?? null,
                'niche_id' => $data['niche_id'] ?? null,
                'origin' => $data['origin'] ?? null,
                'stage' => $stage,
                'temperature' => $data['temperature'] ?? 'warm',
                'priority' => (int) ($data['priority'] ?? 0),
                'position' => $position,
                'closer_id' => $closerId,
                'pre_sales_id' => $preSalesId,
                'ticket_estimado' => $data['ticket_estimado'] ?? null,
                'valor_negociado' => $data['valor_negociado'] ?? null,
                'ticket_fechado' => $data['ticket_fechado'] ?? null,
                'receita_prevista' => $data['receita_prevista'] ?? null,
                'first_contact_at' => $data['first_contact_at'] ?? now(),
                'last_interaction_at' => $data['last_interaction_at'] ?? now(),
                'next_action_at' => $data['next_action_at'] ?? null,
                'meeting_at' => $data['meeting_at'] ?? null,
                'last_stage_change_at' => now(),
                'next_steps' => $data['next_steps'] ?? null,
                'notes' => $data['notes'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
                'company_id' => $data['company_id'] ?? null,
            ]);

            LeadStageHistory::query()->create([
                'lead_id' => $lead->getKey(),
                'from_stage' => null,
                'to_stage' => $stage,
                'reason' => 'Lead criado',
                'user_id' => $actor->getKey(),
            ]);

            LeadInteraction::query()->create([
                'lead_id' => $lead->getKey(),
                'kind' => 'lead_criado',
                'occurred_at' => now(),
                'author' => $actor->name,
                'user_id' => $actor->getKey(),
                'summary' => 'Lead criado no pipeline.',
            ]);

            $this->audit->record('lead.created', $actor, $lead, [], $lead->only($lead->getFillable()), [], $request);

            return $lead->fresh(['niche', 'closer', 'preSales']);
        });
    }

    public function update(Lead $lead, User $actor, array $data, Request $request): Lead
    {
        return DB::transaction(function () use ($lead, $actor, $data, $request) {
            /** @var Lead $locked */
            $locked = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            $allowed = $locked->getFillable();
            $allowed = array_values(array_diff($allowed, [
                'stage', 'loss_reason', 'disqualify_reason', 'position', 'last_stage_change_at',
            ]));

            if ($actor->isSdr()) {
                $allowed = array_values(array_intersect($allowed, [
                    'name', 'company', 'phone', 'whatsapp', 'email', 'niche_id', 'origin',
                    'temperature', 'next_action_at', 'notes', 'pre_sales_id', 'next_steps', 'company_id',
                ]));
            }

            if ($actor->isCloser() && $locked->closer_id !== $actor->getKey()) {
                throw new \Illuminate\Auth\Access\AuthorizationException('Lead não pertence ao usuário.');
            }

            $changes = Arr::only($data, $allowed);
            $old = $locked->only(array_keys($changes));

            $locked->fill($changes);

            if (! empty($changes['last_interaction_at']) || ! empty($changes['next_action_at'])) {
                // mantém os casts de data do modelo
            }

            $locked->save();

            if ($changes !== []) {
                $this->audit->record('lead.updated', $actor, $locked, $old, $locked->only(array_keys($changes)), [], $request);
            }

            return $locked->fresh(['niche', 'closer', 'preSales']);
        });
    }

    public function moveStage(Lead $lead, User $actor, string $toStage, ?string $reason, array $extra, Request $request): Lead
    {
        if (! in_array($toStage, Lead::STAGES, true)) {
            throw ValidationException::withMessages(['to_stage' => 'Etapa inválida.']);
        }

        if ($toStage === 'loss' && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo de Loss.']);
        }

        if ($toStage === 'mal_qualificado' && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Informe o motivo da desqualificação.']);
        }

        if ($actor->isSdr() && ! in_array($toStage, ['lead_agendado', 'fup', 'remarcado', 'mal_qualificado'], true)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('O perfil SDR não pode mover leads para esta etapa.');
        }

        return DB::transaction(function () use ($lead, $actor, $toStage, $reason, $extra, $request) {
            /** @var Lead $locked */
            $locked = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            if ($locked->stage === $toStage) {
                return $locked;
            }

            $from = $locked->stage;
            $old = $locked->only(['stage', 'loss_reason', 'disqualify_reason', 'ticket_fechado', 'temperature', 'position', 'next_action_at', 'next_steps', 'valor_negociado']);

            $extras = Arr::only($extra, ['temperature', 'position', 'next_action_at', 'next_steps', 'valor_negociado', 'commission_percentage', 'commission_amount', 'meeting_at']);
            $locked->fill($extras);
            $locked->stage = $toStage;
            $locked->last_stage_change_at = now();

            if ($toStage === 'loss') {
                $locked->loss_reason = $reason;
            }

            if ($toStage === 'mal_qualificado') {
                $locked->disqualify_reason = $reason;
            }

            if ($toStage === 'fechado') {
                $closedValue = $extra['ticket_fechado'] ?? $locked->ticket_fechado ?? $locked->valor_negociado ?? $locked->ticket_estimado;
                $locked->ticket_fechado = $closedValue;
                $locked->valor_negociado = $locked->valor_negociado ?? $closedValue;
                $locked->receita_prevista = $locked->receita_prevista ?? $closedValue;

                if (isset($extra['saldo_devedor'])) {
                    $locked->saldo_devedor = $extra['saldo_devedor'];
                } elseif ($locked->saldo_devedor === null) {
                    $locked->saldo_devedor = $closedValue;
                }

                $locked->next_action_at = null;
            }

            if (in_array($toStage, ['loss', 'mal_qualificado'], true)) {
                $locked->next_action_at = null;
            }

            $locked->save();

            LeadStageHistory::query()->create([
                'lead_id' => $locked->getKey(),
                'from_stage' => $from,
                'to_stage' => $toStage,
                'reason' => $reason,
                'user_id' => $actor->getKey(),
                'metadata' => array_intersect_key($extra, array_flip(['ticket_fechado', 'saldo_devedor', 'temperature', 'position', 'next_action_at', 'next_steps', 'valor_negociado', 'commission_percentage', 'commission_amount', 'meeting_at'])),
            ]);

            LeadInteraction::query()->create([
                'lead_id' => $locked->getKey(),
                'kind' => 'etapa_alterada',
                'occurred_at' => now(),
                'author' => $actor->name,
                'user_id' => $actor->getKey(),
                'summary' => sprintf(
                    'Etapa alterada de %s para %s%s.',
                    Lead::STAGE_LABELS[$from] ?? $from,
                    Lead::STAGE_LABELS[$toStage] ?? $toStage,
                    $reason ? ' — '.$reason : ''
                ),
            ]);

            $locked->forceFill(['last_interaction_at' => now()])->save();

            if (in_array($toStage, ['fechado', 'loss', 'mal_qualificado'], true)) {
                Reminder::query()
                    ->where('lead_id', $locked->getKey())
                    ->where('status', Reminder::STATUS_PENDING)
                    ->update([
                        'status' => Reminder::STATUS_CANCELLED,
                        'updated_at' => now(),
                    ]);
            }

            $this->audit->record('lead.stage_changed', $actor, $locked, $old, [
                'stage' => $toStage,
                'reason' => $reason,
            ], ['from' => $from, 'to' => $toStage], $request);

            if ($toStage === 'fechado') {
                $this->reminders->syncForLead($locked);
            }

            return $locked->fresh(['niche', 'closer', 'preSales']);
        });
    }

    public function addInteraction(Lead $lead, User $actor, array $data, Request $request): LeadInteraction
    {
        return DB::transaction(function () use ($lead, $actor, $data, $request) {
            $occurredAt = isset($data['occurred_at']) ? now()->parse($data['occurred_at']) : now();

            /** @var LeadInteraction $interaction */
            $interaction = LeadInteraction::query()->create([
                'lead_id' => $lead->getKey(),
                'kind' => $data['kind'],
                'occurred_at' => $occurredAt,
                'author' => $data['author'] ?? $actor->name,
                'user_id' => $actor->getKey(),
                'summary' => $data['summary'],
                'call_id' => $data['call_id'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            if (! $lead->last_interaction_at || $occurredAt->gt($lead->last_interaction_at)) {
                $lead->forceFill(['last_interaction_at' => $occurredAt])->save();
            }

            $this->audit->record('lead.interaction_added', $actor, $lead, [], ['kind' => $data['kind']], [], $request);

            return $interaction;
        });
    }
}
