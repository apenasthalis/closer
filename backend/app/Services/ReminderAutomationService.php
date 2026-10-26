<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Proposal;
use App\Models\Reminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReminderAutomationService
{
    public function __construct(
        private readonly ReminderService $reminders,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Regras explícitas e idempotentes. A mesma regra não cria duplicatas no mesmo
     * dia e nenhuma regra cria uma segunda tarefa pendente para o mesmo lead.
     *
     * @return array{created:int, skipped:int, rules:array<string,int>}
     */
    public function run(): array
    {
        $created = 0;
        $skipped = 0;
        $rules = [];

        $record = function (string $rule, bool $didCreate) use (&$created, &$skipped, &$rules): void {
            $rules[$rule] = ($rules[$rule] ?? 0) + 1;

            if ($didCreate) {
                $created++;
            } else {
                $skipped++;
            }
        };

        Proposal::query()
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->where('sent_at', '<=', now()->subDays(2))
            ->with('lead')
            ->chunkById(100, function ($proposals) use ($record) {
                foreach ($proposals as $proposal) {
                    if (! $proposal->lead) {
                        continue;
                    }

                    $due = now()->setTime(9, 0);
                    $didCreate = $this->createIfMissing(
                        $proposal->lead,
                        'proposal_followup',
                        'proposta',
                        'Retorno de proposta — '.$proposal->lead->name,
                        'Proposta '.$proposal->number.' enviada em '.$proposal->sent_at?->format('d/m/Y').' sem retorno registrado.',
                        $due,
                        ['proposal_id' => $proposal->getKey(), 'proposal_number' => $proposal->number],
                        'proposal:'.$proposal->getKey()
                    );
                    $record('proposal_followup', $didCreate);
                }
            });

        Payment::query()
            ->where('status', 'pending')
            ->whereDate('due_at', '<=', now()->addDays(3)->toDateString())
            ->with('lead')
            ->chunkById(100, function ($payments) use ($record) {
                foreach ($payments as $payment) {
                    if (! $payment->lead) {
                        continue;
                    }

                    $due = Carbon::parse($payment->due_at)->setTime(9, 0);
                    $didCreate = $this->createIfMissing(
                        $payment->lead,
                        'payment_due',
                        $payment->installment_number > 1 ? 'parcela' : 'cobranca',
                        'Cobrança de parcela — '.$payment->lead->name,
                        'Parcela '.$payment->installment_number.' de R$ '.number_format((float) $payment->amount, 2, ',', '.').' vence em '.$due->format('d/m/Y').'.',
                        $due,
                        ['payment_id' => $payment->getKey(), 'amount' => (float) $payment->amount],
                        'payment:'.$payment->getKey()
                    );
                    $record('payment_due', $didCreate);
                }
            });

        Call::query()
            ->where('status', 'scheduled')
            ->whereBetween('started_at', [now(), now()->addHours(26)])
            ->with('lead')
            ->get()
            ->each(function (Call $call) use ($record) {
                if (! $call->lead) {
                    return;
                }

                $due = $call->started_at->copy()->subHours(2);
                $didCreate = $this->createIfMissing(
                    $call->lead,
                    'call_confirmation',
                    'confirmacao_reuniao',
                    'Confirmar reunião — '.$call->lead->name,
                    'Reunião agendada para '.$call->started_at->format('d/m/Y H:i').'.',
                    $due,
                    ['call_id' => $call->getKey()],
                    'call:'.$call->getKey()
                );
                $record('call_confirmation', $didCreate);
            });

        Call::query()
            ->where('status', 'no_show')
            ->where('started_at', '>=', now()->subDays(7))
            ->with('lead')
            ->get()
            ->each(function (Call $call) use ($record) {
                if (! $call->lead) {
                    return;
                }

                $didCreate = $this->createIfMissing(
                    $call->lead,
                    'no_show_recovery',
                    'no_show',
                    'Recuperar no-show — '.$call->lead->name,
                    'O lead não compareceu à reunião de '.$call->started_at?->format('d/m/Y H:i').'.',
                    now()->addHours(3),
                    ['call_id' => $call->getKey()],
                    'call:'.$call->getKey()
                );
                $record('no_show_recovery', $didCreate);
            });

        Contract::query()
            ->where('status', 'pending_signature')
            ->with('lead')
            ->get()
            ->each(function (Contract $contract) use ($record) {
                if (! $contract->lead) {
                    return;
                }

                $didCreate = $this->createIfMissing(
                    $contract->lead,
                    'contract_signature',
                    'contrato',
                    'Contrato aguardando assinatura — '.$contract->lead->name,
                    'Contrato '.$contract->number.' ainda não assinado.',
                    now()->addDay()->setTime(10, 0),
                    ['contract_id' => $contract->getKey()],
                    'contract:'.$contract->getKey()
                );
                $record('contract_signature', $didCreate);
            });

        Lead::query()
            ->whereNotIn('stage', ['fechado', 'loss', 'mal_qualificado'])
            ->where(function ($query) {
                $query->where('last_interaction_at', '<=', now()->subDays(3))
                    ->orWhere(function ($inner) {
                        $inner->whereNull('last_interaction_at')->where('created_at', '<=', now()->subDays(3));
                    });
            })
            ->chunkById(100, function ($leads) use ($record) {
                foreach ($leads as $lead) {
                    $due = now()->addDay()->setTime(9, 0);
                    $didCreate = $this->createIfMissing(
                        $lead,
                        'stale_followup',
                        'follow_up',
                        'Follow-up sem resposta — '.$lead->name,
                        'Sem interação registrada desde '.($lead->last_interaction_at ?? $lead->created_at)?->format('d/m/Y').'.',
                        $due,
                        ['stage' => $lead->stage],
                        'lead:'.$lead->getKey()
                    );
                    $record('stale_followup', $didCreate);
                }
            });

        $this->audit->record('reminders.automation_run', null, null, [], [], [
            'created' => $created,
            'skipped' => $skipped,
            'rules' => $rules,
        ]);

        return ['created' => $created, 'skipped' => $skipped, 'rules' => $rules];
    }

    private function createIfMissing(
        Lead $lead,
        string $ruleKey,
        string $type,
        string $title,
        string $detail,
        Carbon $dueAt,
        array $meta,
        string $unique
    ): bool {
        $window = $dueAt->toDateString();
        $dedupeKey = substr($ruleKey.':'.sha1($unique.'|'.$window), 0, 150);

        return DB::transaction(function () use ($lead, $ruleKey, $type, $title, $detail, $dueAt, $meta, $dedupeKey, $window) {
            $exists = Reminder::query()
                ->where('dedupe_key', $dedupeKey)
                ->exists()
                || Reminder::query()
                    ->where('lead_id', $lead->getKey())
                    ->where('rule_key', $ruleKey)
                    ->where('status', Reminder::STATUS_PENDING)
                    ->exists();

            if ($exists) {
                return false;
            }

            /** @var Reminder $reminder */
            $reminder = Reminder::query()->create([
                'lead_id' => $lead->getKey(),
                'type' => $type,
                'title' => $title,
                'detail' => $detail,
                'due_at' => $dueAt,
                'status' => Reminder::STATUS_PENDING,
                'origin' => 'automation',
                'rule_key' => $ruleKey,
                'dedupe_key' => $dedupeKey,
                'rule_meta' => $meta + ['rule' => $ruleKey, 'window' => $window],
                'responsible_id' => $lead->closer_id ?? $lead->pre_sales_id,
                'priority' => 0,
                'last_rule_run_at' => now(),
            ]);

            $this->reminders->recalculatePriority($reminder);

            return true;
        });
    }
}
