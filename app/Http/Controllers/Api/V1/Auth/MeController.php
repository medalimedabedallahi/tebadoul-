<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        $user = $request->user();

        Gate::authorize('view', $user);

        return new UserResource($user);
    }
}
