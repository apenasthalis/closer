<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        $query = User::query()->with('team:id,name')->orderBy('name');

        if (! $request->user()->isAdmin()) {
            $query->where('team_id', $request->user()->team_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isManager(), 403);
        $query = User::query()
            ->where('active', true)
            ->select(['id', 'name', 'email', 'role', 'team_id'])
            ->with('team:id,name')
            ->orderBy('name');

        if (! $request->user()->isAdmin() && $request->user()->team_id) {
            $query->where('team_id', $request->user()->team_id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function store(UserRequest $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validated();
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'active' => $data['active'] ?? true,
            'phone' => $data['phone'] ?? null,
            'avatar_color' => $data['avatar_color'] ?? '#3fa373',
            'team_id' => $data['team_id'] ?? null,
        ]);

        $this->audit->record('user.created', $request->user(), $user, [], $user->only(['name', 'email', 'role', 'active']), [], $request);

        return response()->json(['data' => $user->load('team:id,name'), 'message' => 'Usuário criado.'], 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validated();

        $willRemainAdmin = $data['role'] === User::ROLE_ADMIN && ($data['active'] ?? $user->active);
        $isCurrentlyAdmin = $user->isAdmin() && $user->active;

        if ($isCurrentlyAdmin && ! $willRemainAdmin && User::query()->where('role', User::ROLE_ADMIN)->where('active', true)->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'Não é possível remover o último administrador ativo.']);
        }

        $old = $user->only(['name', 'email', 'role', 'active', 'team_id']);
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'active' => $data['active'] ?? $user->active,
            'phone' => $data['phone'] ?? null,
            'avatar_color' => $data['avatar_color'] ?? $user->avatar_color,
            'team_id' => $data['team_id'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();
        $this->audit->record('user.updated', $request->user(), $user, $old, $user->only(array_keys($old)), [], $request);

        return response()->json(['data' => $user->fresh('team:id,name'), 'message' => 'Usuário atualizado.']);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar_color' => ['nullable', 'string', 'max:20'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', 'min:10'],
        ]);

        $user = $request->user();

        if (! empty($data['password'])) {
            if (! Hash::check($data['current_password'] ?? '', $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Senha atual incorreta.']);
            }

            $user->password = $data['password'];
        }

        $user->fill($request->only(['name', 'phone', 'avatar_color']));
        $user->save();

        return response()->json(['data' => $user->fresh(), 'message' => 'Perfil atualizado.']);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($user->getKey() === $request->user()->getKey()) {
            throw ValidationException::withMessages(['user' => 'Não é possível desativar a própria conta.']);
        }

        if ($user->isAdmin() && $user->active && User::query()->where('role', User::ROLE_ADMIN)->where('active', true)->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'Não é possível desativar o último administrador ativo.']);
        }

        $user->forceFill(['active' => false])->save();
        $user->authSessions()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_reason' => 'user_deactivated']);
        $this->audit->record('user.deactivated', $request->user(), $user, ['active' => true], ['active' => false], [], $request);

        return response()->json(['message' => 'Usuário desativado.']);
    }
}
