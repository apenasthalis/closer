<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ObjectionRequest;
use App\Models\Objection;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ObjectionController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'niche_id' => ['nullable', 'uuid'],
            'category' => ['nullable', 'string', 'max:60'],
            'stage' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Objection::query()
            ->with(['niche:id,name', 'closer:id,name'])
            ->when($filters['niche_id'] ?? null, fn ($builder, $niche) => $builder->where('niche_id', $niche))
            ->when($filters['category'] ?? null, fn ($builder, $category) => $builder->where('category', $category))
            ->when($filters['stage'] ?? null, fn ($builder, $stage) => $builder->where('stage', $stage))
            ->when($filters['q'] ?? null, fn ($builder, $term) => $builder->whereRaw('lower(label) like ?', ['%'.mb_strtolower($term).'%']));

        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            $query->where(function ($inner) use ($request) {
                $inner->whereNull('closer_id')->orWhere('closer_id', $request->user()->getKey());
            });
        }

        $rows = $query->orderByDesc('times_raised')->get();

        return response()->json([
            'data' => $rows,
            'summary' => [
                'total' => $rows->count(),
                'raised' => (int) $rows->sum('times_raised'),
                'resolved' => (int) $rows->sum('times_resolved'),
                'resolution_rate' => (int) $rows->sum('times_raised') > 0
                    ? round($rows->sum('times_resolved') / $rows->sum('times_raised') * 100, 1)
                    : null,
                'by_category' => $rows->groupBy(fn (Objection $objection) => $objection->category ?: 'sem_categoria')
                    ->map(fn ($group, $category) => [
                        'category' => $category,
                        'count' => $group->count(),
                        'raised' => (int) $group->sum('times_raised'),
                        'resolved' => (int) $group->sum('times_resolved'),
                    ])->values(),
            ],
        ]);
    }

    public function store(ObjectionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $objection = Objection::query()->create([
            'niche_id' => $data['niche_id'] ?? null,
            'label' => $data['label'],
            'category' => $data['category'] ?? null,
            'stage' => $data['stage'] ?? null,
            'context' => $data['context'] ?? null,
            'times_raised' => $data['times_raised'] ?? 1,
            'times_resolved' => $data['times_resolved'] ?? 0,
            'what_worked' => $data['what_worked'] ?? null,
            'best_rebuttal' => $data['best_rebuttal'] ?? null,
            'result' => $data['result'] ?? null,
            'closer_id' => $user->isAdmin() || $user->isManager() ? null : $user->getKey(),
            'last_seen_at' => now(),
        ]);

        $this->audit->record('objection.created', $user, $objection, [], $objection->only(['label', 'category']), [], $request);

        return response()->json(['data' => $objection->load('niche:id,name', 'closer:id,name'), 'message' => 'Objeção registrada.'], 201);
    }

    public function update(ObjectionRequest $request, Objection $objection): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || $user->isManager() || $objection->closer_id === $user->getKey(),
            403
        );

        $old = $objection->only(['label', 'category', 'times_raised', 'times_resolved', 'result']);
        $objection->fill($request->validated());
        $objection->last_seen_at = now();
        $objection->save();
        $this->audit->record('objection.updated', $user, $objection, $old, $objection->only(array_keys($old)), [], $request);

        return response()->json(['data' => $objection->fresh(['niche:id,name', 'closer:id,name']), 'message' => 'Objeção atualizada.']);
    }

    public function destroy(Request $request, Objection $objection): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        $objection->delete();

        return response()->json(['message' => 'Objeção removida.']);
    }
}
