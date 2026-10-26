<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'closer_id' => ['nullable', 'integer', 'exists:users,id'],
            'pre_sales_id' => ['nullable', 'integer', 'exists:users,id'],
            'niche_id' => ['nullable', 'uuid', 'exists:niches,id'],
            'origin' => ['nullable', 'string', 'max:60'],
            'stage' => ['nullable', 'string', 'max:32'],
            'temperature' => ['nullable', 'in:cold,warm,hot'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ]);

        if (! empty($filters['period'])) {
            $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $filters['period'])->startOfMonth();
            $filters['from'] = $start->toDateString();
            $filters['to'] = $start->copy()->endOfMonth()->toDateString();
        }

        $user = $request->user();

        if (! $user->isAdmin() && ! $user->isManager()) {
            $filters['closer_id'] = $user->getKey();
            $filters['pre_sales_id'] = null;
            $filters['team_id'] = null;
        } elseif ($user->isManager() && $user->team_id) {
            $filters['team_id'] = $user->team_id;
        }

        return response()->json(['data' => $this->dashboard->build($user, $filters)]);
    }

    public function losses(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'period' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'niche_id' => ['nullable', 'uuid', 'exists:niches,id'],
            'closer_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        return response()->json(['data' => $this->dashboard->lossAnalysis($request->user(), $filters)]);
    }
}
