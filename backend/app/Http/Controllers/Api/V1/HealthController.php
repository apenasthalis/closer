<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        try {
            DB::select('select 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (Throwable $exception) {
            $healthy = false;
            $checks['database'] = ['status' => 'error', 'message' => 'Banco indisponível.'];
        }

        $storagePath = storage_path('app/private');
        $writable = is_dir($storagePath) && is_writable($storagePath);
        $checks['storage'] = ['status' => $writable ? 'ok' : 'error'];
        $healthy = $healthy && $writable;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => 'closer-api',
            'version' => '1.0.0',
            'time' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }
}
