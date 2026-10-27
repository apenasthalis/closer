<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommissionRuleRequest;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Services\AuditService;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommissionController extends Controller
{
    public function __construct(
        private readonly CommissionService $commissions,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['estimated', 'calculated', 'approved', 'paid', 'reversed'])],
        ]);

        $user = $request->user();
        $query = Commission::query()
            ->with(['lead:id,name,company', 'user:id,name', 'rule:id,name'])
            ->where('user_id', $user->getKey())
            ->when($filters['from'] ?? null, fn ($builder, $from) => $builder->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($builder, $to) => $builder->whereDate('created_at', '<=', $to))
            ->when(! ($filters['from'] ?? null) && ! ($filters['to'] ?? null) && ($filters['period'] ?? null), fn ($builder, $period) => $builder->where('period', $period))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status));

        $rows = $query->latest()->get();

        return response()->json([
            'data' => $rows,
            'summary' => collect(['estimated', 'calculated', 'approved', 'paid', 'reversed'])
                ->mapWithKeys(fn ($status) => [$status => (float) $rows->where('status', $status)->sum('amount')])
                ->all(),
        ]);
    }

    public function calculate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        if ($from && $to) {
            $start = \Illuminate\Support\Carbon::parse($from)->startOfMonth();
            $end = \Illuminate\Support\Carbon::parse($to)->startOfMonth();
            $periods = [];
            while ($start->lte($end)) {
                $periods[] = $start->format('Y-m');
                $start->addMonth();
            }
            $created = 0;
            $skipped = 0;
            foreach ($periods as $period) {
                $result = $this->commissions->calculatePeriod($request->user(), $period);
                $created += $result['calculated'];
                $skipped += $result['skipped'];
            }
            return response()->json(['data' => ['period' => $data['period'] ?? $periods[0], 'from' => $from, 'to' => $to, 'calculated' => $created, 'skipped' => $skipped, 'months' => count($periods)], 'message' => count($periods) > 1 ? "Comissões calculadas para ${count($periods)} mês(es)." : 'Comissões calculadas.']);
        }

        $period = $data['period'] ?? now()->format('Y-m');
        $result = $this->commissions->calculatePeriod($request->user(), $period);

        return response()->json(['data' => $result, 'message' => 'Comissões calculadas.']);
    }

    public function approve(Request $request, Commission $commission): JsonResponse
    {
        abort_unless($request->user()->canApproveCommissions(), 403);
        $commission = $this->commissions->approve($commission, $request->user());

        return response()->json(['data' => $commission, 'message' => 'Comissão aprovada.']);
    }

    public function markPaid(Request $request, Commission $commission): JsonResponse
    {
        abort_unless($request->user()->canApproveCommissions(), 403);
        $commission = $this->commissions->markPaid($commission, $request->user());

        return response()->json(['data' => $commission, 'message' => 'Comissão marcada como paga.']);
    }

    public function reverse(Request $request, Commission $commission): JsonResponse
    {
        abort_unless($request->user()->canApproveCommissions(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $commission = $this->commissions->reverse($commission, $request->user(), $data['reason']);

        return response()->json(['data' => $commission, 'message' => 'Comissão estornada.']);
    }

    public function rules(Request $request): JsonResponse
    {
        abort_unless($request->user()->canViewFinancials(), 403);

        return response()->json([
            'data' => CommissionRule::query()->with(['user:id,name', 'team:id,name', 'niche:id,name'])->orderByDesc('priority')->get(),
        ]);
    }

    public function storeRule(CommissionRuleRequest $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $rule = CommissionRule::query()->create($request->validated() + [
            'active' => $request->boolean('active', true),
            'priority' => $request->integer('priority', 0),
            'created_by' => $request->user()->getKey(),
        ]);

        $this->audit->record('commission_rule.created', $request->user(), $rule, [], $rule->only(['name', 'scope', 'percentage']), [], $request);

        return response()->json(['data' => $rule, 'message' => 'Regra criada.'], 201);
    }

    public function updateRule(CommissionRuleRequest $request, CommissionRule $rule): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $old = $rule->only(['name', 'scope', 'percentage', 'fixed_amount', 'active']);
        $rule->fill($request->validated());
        $rule->active = $request->has('active') ? $request->boolean('active') : $rule->active;
        $rule->save();
        $this->audit->record('commission_rule.updated', $request->user(), $rule, $old, $rule->only(array_keys($old)), [], $request);

        return response()->json(['data' => $rule, 'message' => 'Regra atualizada.']);
    }

    public function destroyRule(Request $request, CommissionRule $rule): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $rule->forceFill(['active' => false])->save();
        $this->audit->record('commission_rule.deactivated', $request->user(), $rule, ['active' => true], ['active' => false], [], $request);

        return response()->json(['message' => 'Regra desativada.']);
    }
}
