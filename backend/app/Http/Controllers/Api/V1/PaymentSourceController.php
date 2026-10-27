<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentSourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sources = PaymentSource::query()
            ->where('active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $sources]);
    }
}
