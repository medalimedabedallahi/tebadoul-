<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'ok',
                'service' => 'badal-api',
                'version' => 'v1',
                'request_id' => $request->attributes->get('request_id'),
                'timestamp' => now()->utc()->toIso8601String(),
            ],
        ]);
    }
}
