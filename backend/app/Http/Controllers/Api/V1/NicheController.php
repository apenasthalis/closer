<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Niche;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NicheController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Niche::query()->withCount('leads')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('niches', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'active' => ['nullable', 'boolean'],
        ]);

        $niche = Niche::query()->create($data + [
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
            'color' => $data['color'] ?? '#3fa373',
            'active' => $request->boolean('active', true),
        ]);

        return response()->json(['data' => $niche, 'message' => 'Nicho criado.'], 201);
    }

    public function update(Request $request, Niche $niche): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120', Rule::unique('niches', 'name')->ignore($niche->getKey())],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'active' => ['nullable', 'boolean'],
        ]);

        $niche->fill($data);
        $niche->save();

        return response()->json(['data' => $niche, 'message' => 'Nicho atualizado.']);
    }
}
