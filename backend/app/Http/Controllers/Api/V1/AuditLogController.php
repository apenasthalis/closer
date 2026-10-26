<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:80'],
            'entity_type' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name')
            ->when($filters['user_id'] ?? null, fn ($builder, $id) => $builder->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($builder, $action) => $builder->where('action', 'like', $action.'%'))
            ->when($filters['entity_type'] ?? null, fn ($builder, $type) => $builder->where('entity_type', $type))
            ->when($filters['from'] ?? null, fn ($builder, $from) => $builder->where('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($builder, $to) => $builder->where('created_at', '<=', $to.' 23:59:59'))
            ->latest('created_at');

        return response()->json($query->paginate(50)->withQueryString());
    }
}
