<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentSource;
use App\Models\Reminder;
use App\Services\AuditService;
use App\Services\CommissionService;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaleController extends Controller
{
    public function __construct(
        private readonly LeadService $leads,
        private readonly CommissionService $commissions,
        private readonly AuditService $audit,
    ) {
    }

    public function store(Request $request, Lead $lead): JsonResponse
    {
        abort_unless(
            Lead::query()->visibleTo($request->user())->whereKey($lead->getKey())->exists(),
            403,
            'Lead não acessível.'
        );

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'source' => ['required', 'string', Rule::exists('payment_sources', 'slug')->where('active', true)],
            'status' => ['nullable', Rule::in(['pending', 'paid'])],
            'due_at' => ['required', 'date'],
            'kind' => ['nullable', Rule::in(['entry', 'installment', 'other'])],
            'commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commission_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'next_action_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $actor = $request->user();
        $source = PaymentSource::query()->where('slug', $data['source'])->firstOrFail();
        $status = $data['status'] ?? 'paid';

        [$lead, $payment] = DB::transaction(function () use ($actor, $lead, $data, $source, $status, $request) {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            if (! $lead->closer_id) {
                $lead->forceFill(['closer_id' => $actor->getKey()])->save();
            }

            if ($lead->stage !== 'fechado') {
                $lead = $this->leads->moveStage($lead, $actor, 'fechado', null, [
                    'ticket_fechado' => $data['amount'],
                ], $request);
            } else {
                $lead->forceFill([
                    'ticket_fechado' => $data['amount'],
                    'valor_negociado' => $lead->valor_negociado ?? $data['amount'],
                    'receita_prevista' => $lead->receita_prevista ?? $data['amount'],
                    'saldo_devedor' => $lead->saldo_devedor ?? $data['amount'],
                ])->save();
            }

            $lead->forceFill([
                'commission_percentage' => $data['commission_percentage'] ?? $lead->commission_percentage,
                'commission_amount' => $data['commission_amount'] ?? $lead->commission_amount,
            ])->save();

            $payment = Payment::query()->create([
                'lead_id' => $lead->getKey(),
                'kind' => $data['kind'] ?? 'entry',
                'installment_number' => 1,
                'amount' => $data['amount'],
                'due_at' => $data['due_at'],
                'paid_at' => $status === 'paid' ? now() : null,
                'status' => $status,
                'method' => $source->slug,
                'enters_cash' => $source->enters_cash,
                'notes' => $data['notes'] ?? null,
                'next_action' => $data['next_action'] ?? null,
                'next_action_at' => $data['next_action_at'] ?? null,
                'created_by' => $actor->getKey(),
            ]);

            if (! empty($data['next_action'])) {
                Reminder::query()->create([
                    'lead_id' => $lead->getKey(),
                    'type' => 'cobranca',
                    'title' => Str::limit('Próxima ação da venda — '.$lead->name, 180, ''),
                    'detail' => $data['next_action'],
                    'due_at' => $data['next_action_at'] ?? now()->addDays(2)->setTime(9, 0),
                    'status' => Reminder::STATUS_PENDING,
                    'origin' => 'sale',
                    'rule_key' => 'sale_next_action',
                    'dedupe_key' => 'sale:'.$payment->getKey(),
                    'rule_meta' => ['payment_id' => $payment->getKey(), 'source' => $source->slug],
                    'responsible_id' => $lead->closer_id ?? $actor->getKey(),
                    'priority' => 0,
                ]);
            }

            $this->audit->record('sale.created', $actor, $lead, [], [
                'amount' => (float) $data['amount'],
                'source' => $source->slug,
                'enters_cash' => (bool) $source->enters_cash,
                'commission_percentage' => $data['commission_percentage'] ?? null,
                'commission_amount' => $data['commission_amount'] ?? null,
            ], [], $request);

            return [$lead, $payment];
        });

        $commission = $this->commissions->calculateForLead($lead->fresh(), now()->format('Y-m'));

        return response()->json([
            'data' => [
                'lead' => $lead->fresh(['niche:id,name,color', 'closer:id,name', 'preSales:id,name']),
                'payment' => $payment->fresh(),
                'commission' => $commission,
            ],
            'message' => 'Venda registrada com comissão e próxima ação.',
        ], 201);
    }
}
