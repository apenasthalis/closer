<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScriptRequest;
use App\Models\Script;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScriptController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'niche_id' => ['nullable', 'uuid'],
            'stage' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Script::query()
            ->with(['niche:id,name,color', 'updatedBy:id,name', 'playbook:id,name'])
            ->when($filters['niche_id'] ?? null, fn ($builder, $niche) => $builder->where('niche_id', $niche))
            ->when($filters['stage'] ?? null, fn ($builder, $stage) => $builder->where('stage', $stage))
            ->when($filters['category'] ?? null, fn ($builder, $category) => $builder->where('category', $category))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($filters['q'] ?? null, fn ($builder, $term) => $builder->whereRaw('lower(title) like ?', ['%'.mb_strtolower($term).'%']));

        if (! $request->user()->canManagePlaybooks()) {
            $query->where('status', 'published');
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    public function store(ScriptRequest $request): JsonResponse
    {
        abort_unless($request->user()->canManagePlaybooks(), 403);
        $data = $request->validated();
        $status = $data['status'] ?? 'draft';

        $script = Script::query()->create([
            'niche_id' => $data['niche_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'stage' => $data['stage'] ?? null,
            'category' => $data['category'] ?? null,
            'sections' => $data['sections'] ?? null,
            'version' => 1,
            'status' => $status,
            'playbook_id' => $data['playbook_id'] ?? null,
            'updated_by' => $request->user()->getKey(),
            'published_at' => $status === 'published' ? now() : null,
        ]);

        $this->audit->record('script.created', $request->user(), $script, [], $script->only(['title', 'status']), [], $request);

        return response()->json(['data' => $script->load('niche:id,name', 'updatedBy:id,name'), 'message' => 'Script criado.'], 201);
    }

    public function show(Request $request, Script $script): JsonResponse
    {
        abort_unless($request->user()->canManagePlaybooks() || $script->status === 'published', 403);

        return response()->json(['data' => $script->load('niche:id,name', 'updatedBy:id,name', 'playbook:id,name')]);
    }

    public function update(ScriptRequest $request, Script $script): JsonResponse
    {
        abort_unless($request->user()->canManagePlaybooks(), 403);
        $data = $request->validated();
        $old = $script->only(['title', 'status', 'version']);

        $script->fill($data);
        $script->version = $script->version + 1;
        $script->updated_by = $request->user()->getKey();

        if (($data['status'] ?? null) === 'published' && ! $script->published_at) {
            $script->published_at = now();
        }

        $script->save();
        $this->audit->record('script.updated', $request->user(), $script, $old, $script->only(['title', 'status', 'version']), [], $request);

        return response()->json(['data' => $script->fresh(['niche:id,name', 'updatedBy:id,name']), 'message' => 'Script atualizado.']);
    }

    public function destroy(Request $request, Script $script): JsonResponse
    {
        abort_unless($request->user()->canManagePlaybooks(), 403);
        $script->forceFill(['status' => 'archived'])->save();

        return response()->json(['message' => 'Script arquivado.']);
    }
}
