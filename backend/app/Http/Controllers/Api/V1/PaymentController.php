<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentSource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
            'lead_id' => ['nullable', 'uuid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Payment::query()
            ->with(['lead:id,name,company', 'contract:id,number', 'proposal:id,number'])
            ->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['lead_id'] ?? null, fn ($builder, $leadId) => $builder->where('lead_id', $leadId))
            ->when($filters['from'] ?? null, fn ($builder, $from) => $builder->whereDate('due_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($builder, $to) => $builder->whereDate('due_at', '<=', $to))
            ->orderBy('due_at');

        return response()->json([
            'data' => $query->get(),
            'summary' => [
                'pending' => (float) Payment::query()->where('status', 'pending')->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))->sum('amount'),
                'paid' => (float) Payment::query()->where('status', 'paid')->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))->sum('amount'),
                'overdue' => (float) Payment::query()->where('status', 'pending')->whereDate('due_at', '<', now()->toDateString())->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))->sum('amount'),
                'cash_in' => (float) Payment::query()->where('status', 'paid')->where('enters_cash', true)->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))->sum('amount'),
                'cash_out' => (float) Payment::query()->where('status', 'paid')->where('enters_cash', false)->whereHas('lead', fn ($builder) => $builder->visibleTo($request->user()))->sum('amount'),
            ],
        ]);
    }

    public function store(PaymentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->authorizeLead($request, $data['lead_id']);

        $status = $data['status'] ?? 'pending';
        $source = $data['method'] ?? null
            ? PaymentSource::query()->where('slug', $data['method'])->first()
            : null;

        $payment = Payment::query()->create([
            'lead_id' => $data['lead_id'],
            'contract_id' => $data['contract_id'] ?? null,
            'proposal_id' => $data['proposal_id'] ?? null,
            'kind' => $data['kind'],
            'installment_number' => $data['installment_number'] ?? 1,
            'amount' => $data['amount'],
            'due_at' => $data['due_at'],
            'paid_at' => $status === 'paid' ? ($data['paid_at'] ?? now()) : null,
            'status' => $status,
            'method' => $data['method'] ?? null,
            'enters_cash' => $source ? (bool) $source->enters_cash : true,
            'notes' => $data['notes'] ?? null,
            'next_action' => $data['next_action'] ?? null,
            'next_action_at' => $data['next_action_at'] ?? null,
            'created_by' => $request->user()->getKey(),
        ]);

        $this->audit->record('payment.created', $request->user(), $payment, [], $payment->only(['amount', 'status', 'due_at', 'method', 'enters_cash']), [], $request);

        return response()->json(['data' => $payment->load('lead:id,name'), 'message' => 'Pagamento registrado.'], 201);
    }

    public function createInstallments(Request $request, Lead $lead): JsonResponse
    {
        $this->authorizeLead($request, $lead->getKey());
        $data = $request->validate([
            'total' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'installments' => ['required', 'integer', 'min:1', 'max:60'],
            'first_due_at' => ['required', 'date'],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'kind' => ['nullable', Rule::in(['installment', 'entry', 'recurring', 'other'])],
            'method' => ['nullable', 'string', Rule::exists('payment_sources', 'slug')->where('active', true)],
            'next_action' => ['nullable', 'string', 'max:2000'],
        ]);

        $totalCents = (int) round((float) $data['total'] * 100);
        $count = (int) $data['installments'];
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents % $count;
        $due = Carbon::parse($data['first_due_at']);
        $interval = (int) ($data['interval_days'] ?? 30);
        $source = $data['method'] ?? null
            ? PaymentSource::query()->where('slug', $data['method'])->first()
            : null;
        $created = [];

        for ($index = 1; $index <= $count; $index++) {
            $cents = $base + ($index <= $remainder ? 1 : 0);
            $created[] = Payment::query()->create([
                'lead_id' => $lead->getKey(),
                'kind' => $data['kind'] ?? 'installment',
                'installment_number' => $index,
                'amount' => $cents / 100,
                'due_at' => $due->copy()->addDays(($index - 1) * $interval)->toDateString(),
                'status' => 'pending',
                'method' => $data['method'] ?? null,
                'enters_cash' => $source ? (bool) $source->enters_cash : true,
                'next_action' => $data['next_action'] ?? null,
                'created_by' => $request->user()->getKey(),
            ]);
        }

        $this->audit->record('payment.installments_created', $request->user(), $lead, [], [
            'total' => (float) $data['total'],
            'installments' => $count,
        ], [], $request);

        return response()->json(['data' => $created, 'message' => $count.' parcelas criadas.'], 201);
    }

    public function updateStatus(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeLead($request, $payment->lead_id);
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'paid', 'overdue', 'cancelled'])],
            'method' => ['nullable', 'string', Rule::exists('payment_sources', 'slug')->where('active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:2000'],
            'next_action_at' => ['nullable', 'date'],
        ]);

        $old = $payment->only(['status', 'paid_at', 'method', 'enters_cash']);
        $payment->status = $data['status'];

        if (array_key_exists('method', $data) && $data['method']) {
            $payment->method = $data['method'];
            $source = PaymentSource::query()->where('slug', $data['method'])->first();
            $payment->enters_cash = $source ? (bool) $source->enters_cash : $payment->enters_cash;
        }

        if ($data['status'] === 'paid') {
            $payment->paid_at = $payment->paid_at ?? now();
        } else {
            $payment->paid_at = null;
        }

        if (array_key_exists('notes', $data)) {
            $payment->notes = $data['notes'];
        }

        if (array_key_exists('next_action', $data)) {
            $payment->next_action = $data['next_action'];
        }

        if (array_key_exists('next_action_at', $data)) {
            $payment->next_action_at = $data['next_action_at'];
        }

        $payment->save();
        $this->audit->record('payment.status_changed', $request->user(), $payment, $old, $payment->only(['status', 'paid_at', 'method', 'enters_cash']), [], $request);

        return response()->json(['data' => $payment->fresh('lead:id,name'), 'message' => 'Pagamento atualizado.']);
    }

    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $this->authorizeLead($request, $payment->lead_id);
        $payment->delete();

        return response()->json(['message' => 'Pagamento removido.']);
    }

    private function authorizeLead(Request $request, string $leadId): void
    {
        abort_unless(Lead::query()->visibleTo($request->user())->whereKey($leadId)->exists(), 403, 'Lead não acessível.');
    }
}
