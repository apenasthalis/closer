<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Company::query()->withCount('users');

        return response()->json([
            'data' => $query->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('companies', 'name')],
            'cnpj' => ['nullable', 'string', 'max:18', Rule::unique('companies', 'cnpj')],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'active' => ['nullable', 'boolean'],
        ]);

        $company = Company::query()->create($data + [
            'active' => $request->boolean('active', true),
        ]);

        return response()->json(['data' => $company, 'message' => 'Empresa criada.'], 201);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120', Rule::unique('companies', 'name')->ignore($company->getKey())],
            'cnpj' => ['sometimes', 'nullable', 'string', 'max:18', Rule::unique('companies', 'cnpj')->ignore($company->getKey())],
            'email' => ['sometimes', 'nullable', 'email', 'max:180'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $old = $company->only(array_keys($data));
        $company->fill($data)->save();

        return response()->json(['data' => $company->fresh(['users:id,name']), 'message' => 'Empresa atualizada.']);
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $company->delete();

        return response()->json(['message' => 'Empresa excluída.']);
    }
}
