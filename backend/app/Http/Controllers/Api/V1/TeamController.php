<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Team::query()->withCount('users')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('teams', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $team = Team::query()->create($data + [
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'active' => $request->boolean('active', true),
        ]);

        return response()->json(['data' => $team, 'message' => 'Equipe criada.'], 201);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120', Rule::unique('teams', 'name')->ignore($team->getKey())],
            'description' => ['nullable', 'string', 'max:1000'],
            'active' => ['nullable', 'boolean'],
        ]);

        $team->fill($data);
        $team->save();

        return response()->json(['data' => $team, 'message' => 'Equipe atualizada.']);
    }
}
