<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DockerControlService;
use Illuminate\Http\JsonResponse;

class DockerController extends Controller
{
    public function __construct(private readonly DockerControlService $docker)
    {
    }

    public function status(): JsonResponse
    {
        return response()->json(['data' => $this->docker->status()]);
    }

    public function start(): JsonResponse
    {
        $result = $this->docker->start();

        if (! $result['ok']) {
            return response()->json(['message' => $result['error']], 503);
        }

        return response()->json(['data' => $result['result'], 'message' => 'Solicitação de inicialização enviada.']);
    }
}
