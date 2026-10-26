<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Playbook;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlaybookController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Playbook::query()
            ->with('niche:id,name')
            ->when(! $request->user()->canManagePlaybooks(), fn ($builder) => $builder->where('active', true))
            ->latest('version');

        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canManagePlaybooks(), 403);
        $data = $this->validated($request);
        $playbook = Playbook::query()->create($data + [
            'version' => 1,
            'active' => $request->boolean('active', true),
            'created_by' => $request->user()->getKey(),
        ]);

        $this->audit->record('playbook.created', $request->user(), $playbook, [], $playbook->only(['name', 'version']), [], $request);

        return response()->json(['data' => $playbook->load('niche:id,name'), 'message' => 'Playbook criado.'], 201);
    }

    public function update(Request $request, Playbook $playbook): JsonResponse
    {
        abort_unless($request->user()->canManagePlaybooks(), 403);
        $data = $this->validated($request);
        $old = $playbook->only(['name', 'active', 'version']);
        $playbook->fill($data);
        $playbook->active = $request->boolean('active', $playbook->active);
        $playbook->version = $playbook->version + 1;
        $playbook->save();
        $this->audit->record('playbook.updated', $request->user(), $playbook, $old, $playbook->only(['name', 'active', 'version']), [], $request);

        return response()->json(['data' => $playbook->fresh('niche:id,name'), 'message' => 'Playbook atualizado.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'niche_id' => ['nullable', 'uuid', 'exists:niches,id'],
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'criteria' => ['required', 'array', 'min:1', 'max:50'],
            'criteria.*.key' => ['required', 'string', 'max:60', 'distinct'],
            'criteria.*.label' => ['required', 'string', 'max:120'],
            'criteria.*.weight' => ['required', 'integer', 'min:1', 'max:100'],
            'criteria.*.keywords' => ['required', 'array', 'min:1', 'max:50'],
            'criteria.*.keywords.*' => ['string', 'max:80'],
            'active' => ['nullable', 'boolean'],
        ]);
    }
}
