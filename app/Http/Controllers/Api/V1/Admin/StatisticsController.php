<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\ComputePlatformStatistics;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function __invoke(Request $request, ComputePlatformStatistics $statistics): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $statistics->handle($user)]);
    }
}
