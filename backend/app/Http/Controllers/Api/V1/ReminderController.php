<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReminderRequest;
use App\Models\Reminder;
use App\Services\AuditService;
use App\Services\ReminderAutomationService;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ReminderService $reminders,
        private readonly ReminderAutomationService $automation,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'done', 'cancelled', 'all'])],
            'due' => ['nullable', Rule::in(['overdue', 'today', 'week'])],
            'type' => ['nullable', 'string', 'max:32'],
            'responsible_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $query = Reminder::query()
            ->with(['lead:id,name,company,temperature,stage', 'responsible:id,name'])
            ->where('responsible_id', $user->getKey());

        $query->when(($filters['status'] ?? 'pending') !== 'all', function ($builder) use ($filters) {
            $map = ['pending' => 'pendente', 'done' => 'concluido', 'cancelled' => 'cancelado'];
            $builder->where('status', $map[$filters['status'] ?? 'pending']);
        })
            ->when($filters['due'] ?? null, function ($builder, $due) {
                match ($due) {
                    'overdue' => $builder->where('status', 'pendente')->where('due_at', '<', now()),
                    'today' => $builder->whereDate('due_at', now()->toDateString()),
                    'week' => $builder->whereBetween('due_at', [now(), now()->addDays(7)]),
                };
            })
            ->when($filters['type'] ?? null, fn ($builder, $type) => $builder->where('type', $type))
            ->when($filters['responsible_id'] ?? null, fn ($builder, $id) => $builder->where('responsible_id', $id))
            ->orderByRaw("case status when 'pendente' then 0 else 1 end")
            ->orderByDesc('priority')
            ->orderBy('due_at');

        return response()->json($query->paginate(100)->withQueryString());
    }

    public function store(ReminderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['responsible_id'] = $request->user()->getKey();

        $reminder = $this->reminders->create($request->user(), $data, $request);

        return response()->json(['data' => $reminder, 'message' => 'Lembrete criado.'], 201);
    }

    public function update(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorizeReminder($request, $reminder);
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(Reminder::TYPES)],
            'title' => ['sometimes', 'string', 'min:2', 'max:180'],
            'detail' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['sometimes', 'date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:10'],
            'sent_to_lead' => ['nullable', 'boolean'],
        ]);

        $old = $reminder->only(array_keys($data));
        $reminder->fill($data)->save();
        $this->reminders->recalculatePriority($reminder);
        $this->audit->record('reminder.updated', $request->user(), $reminder, $old, $reminder->only(array_keys($data)), [], $request);

        return response()->json(['data' => $reminder->fresh(['lead', 'responsible']), 'message' => 'Lembrete atualizado.']);
    }

    public function complete(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorizeReminder($request, $reminder);
        $reminder = $this->reminders->complete($reminder, $request->user(), $request);

        return response()->json(['data' => $reminder, 'message' => 'Lembrete concluído.']);
    }

    public function snooze(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorizeReminder($request, $reminder);
        $data = $request->validate([
            'due_at' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reminder = $this->reminders->snooze($reminder, $request->user(), $data['due_at'], $data['reason'] ?? null, $request);

        return response()->json(['data' => $reminder, 'message' => 'Lembrete adiado.']);
    }

    public function cancel(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorizeReminder($request, $reminder);
        $reminder = $this->reminders->cancel($reminder, $request->user(), $request);

        return response()->json(['data' => $reminder, 'message' => 'Lembrete cancelado.']);
    }

    public function reassign(Request $request, Reminder $reminder): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        $this->authorizeReminder($request, $reminder);
        $data = $request->validate(['responsible_id' => ['required', 'integer', 'exists:users,id']]);
        $reminder = $this->reminders->reassign($reminder, $request->user(), (int) $data['responsible_id'], $request);

        return response()->json(['data' => $reminder, 'message' => 'Responsável alterado.']);
    }

    public function runAutomation(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        $result = $this->automation->run();

        return response()->json(['data' => $result, 'message' => 'Automação de lembretes executada.']);
    }

    private function authorizeReminder(Request $request, Reminder $reminder): void
    {
        abort_unless($reminder->responsible_id === $request->user()->getKey(), 403, 'Lembrete não acessível.');
    }
}
