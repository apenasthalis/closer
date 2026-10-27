<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CallRequest;
use App\Models\Call;
use App\Models\Lead;
use App\Models\Reminder;
use App\Services\AuditService;
use App\Services\CallAnalysisService;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CallController extends Controller
{
    public function __construct(
        private readonly CallAnalysisService $analysis,
        private readonly ReminderService $reminders,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'lead_id' => ['nullable', 'uuid'],
            'closer_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['scheduled', 'completed', 'no_show', 'cancelled'])],
            'analysis_status' => ['nullable', Rule::in(['not_requested', 'pending', 'completed', 'failed'])],
        ]);

        $user = $request->user();
        $query = Call::query()
            ->with(['lead:id,name,company,stage', 'closer:id,name'])
            ->where('closer_id', $user->getKey())
            ->when($filters['lead_id'] ?? null, fn ($builder, $id) => $builder->where('lead_id', $id))
            ->when($filters['closer_id'] ?? null, fn ($builder, $id) => $builder->where('closer_id', $id))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['analysis_status'] ?? null, fn ($builder, $status) => $builder->where('analysis_status', $status));

        return response()->json($query->latest('started_at')->paginate(50)->withQueryString());
    }

    public function store(CallRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $data['closer_id'] = $user->getKey();

        if (! empty($data['lead_id'])) {
            $this->authorizeLead($request, $data['lead_id']);
        }

        $call = Call::query()->create([
            'lead_id' => $data['lead_id'] ?? null,
            'closer_id' => $data['closer_id'] ?? $user->getKey(),
            'title' => $data['title'],
            'status' => $data['status'] ?? 'completed',
            'started_at' => $data['started_at'] ?? now(),
            'duration_sec' => $data['duration_sec'] ?? 0,
            'transcript' => $data['transcript'] ?? null,
            'analysis_status' => ! empty($data['transcript']) ? 'pending' : 'not_requested',
        ]);

        if ($call->lead_id) {
            $lead = Lead::query()->find($call->lead_id);
            $lead?->forceFill(['last_interaction_at' => now()])->save();
            \App\Models\LeadInteraction::query()->create([
                'lead_id' => $call->lead_id,
                'kind' => 'ligacao',
                'occurred_at' => $call->started_at ?? now(),
                'author' => $user->name,
                'user_id' => $user->getKey(),
                'summary' => 'Call registrada: '.$call->title,
                'call_id' => $call->getKey(),
            ]);
        }

        $this->audit->record('call.created', $user, $call, [], $call->only(['title', 'status', 'started_at']), [], $request);

        return response()->json(['data' => $call->load(['lead:id,name', 'closer:id,name']), 'message' => 'Call registrada.'], 201);
    }

    public function show(Request $request, Call $call): JsonResponse
    {
        $this->authorizeCall($request, $call);

        return response()->json(['data' => $call->load(['lead:id,name,company,stage', 'closer:id,name'])]);
    }

    public function update(CallRequest $request, Call $call): JsonResponse
    {
        $this->authorizeCall($request, $call);
        $data = $request->validated();
        unset($data['closer_id']);

        if (! empty($data['lead_id'])) {
            $this->authorizeLead($request, $data['lead_id']);
        }

        $old = $call->only(array_keys($data));
        $call->fill($data);

        if (array_key_exists('transcript', $data)) {
            $call->analysis_status = 'pending';
        }

        $call->save();
        $this->audit->record('call.updated', $request->user(), $call, $old, $call->only(array_keys($data)), [], $request);

        return response()->json(['data' => $call->fresh(['lead:id,name', 'closer:id,name']), 'message' => 'Call atualizada.']);
    }

    public function analyze(Request $request, Call $call): JsonResponse
    {
        $this->authorizeCall($request, $call);
        $data = $request->validate([
            'playbook_id' => ['nullable', 'integer', 'exists:playbooks,id'],
        ]);

        $playbook = ! empty($data['playbook_id'])
            ? \App\Models\Playbook::query()->find($data['playbook_id'])
            : ($call->lead?->niche_id
                ? \App\Models\Playbook::query()->where('niche_id', $call->lead->niche_id)->where('active', true)->latest('version')->first()
                : null);

        $call = $this->analysis->analyze($call, $request->user(), $playbook);

        return response()->json(['data' => $call, 'message' => 'Call analisada localmente.']);
    }

    public function createNextSteps(Request $request, Call $call): JsonResponse
    {
        $this->authorizeCall($request, $call);
        $data = $request->validate([
            'due_at' => ['nullable', 'date', 'after:now'],
            'steps' => ['nullable', 'array', 'max:10'],
            'steps.*.title' => ['required_with:steps', 'string', 'max:180'],
            'steps.*.context' => ['nullable', 'string', 'max:2000'],
        ]);

        $steps = $data['steps'] ?? ($call->extracted_steps ?? []);

        if ($steps === [] || ! $call->lead_id) {
            return response()->json(['message' => 'Nenhum próximo passo disponível para criar tarefas.'], 422);
        }

        $created = [];

        foreach (array_slice($steps, 0, 10) as $step) {
            $created[] = $this->reminders->create($request->user(), [
                'lead_id' => $call->lead_id,
                'type' => 'follow_up',
                'title' => mb_substr((string) ($step['title'] ?? 'Follow-up da call'), 0, 180),
                'detail' => $step['context'] ?? null,
                'due_at' => $data['due_at'] ?? now()->addDays(2)->setTime(9, 0)->toIso8601String(),
                'origin' => 'call_analysis',
                'rule_key' => 'call_next_step',
                'dedupe_key' => 'call-step:'.$call->getKey().':'.sha1((string) ($step['title'] ?? '')),
                'responsible_id' => $call->closer_id ?? $request->user()->getKey(),
            ], $request);
        }

        return response()->json(['data' => $created, 'message' => count($created).' tarefa(s) criada(s).'], 201);
    }

    private function authorizeCall(Request $request, Call $call): void
    {
        abort_unless($call->closer_id === $request->user()->getKey(), 403, 'Call não acessível.');
    }

    private function authorizeLead(Request $request, string $leadId): void
    {
        abort_unless(
            Lead::query()->visibleTo($request->user())->whereKey($leadId)->exists(),
            403,
            'Lead não acessível.'
        );
    }
}
