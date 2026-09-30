<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\AuthTokenResource;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginUser $loginUser): AuthTokenResource
    {
        $token = $loginUser->handle(
            identifier: $request->string('identifier')->toString(),
            password: $request->string('password')->toString(),
            deviceName: $request->string('device_name', 'api')->toString(),
        );

        return new AuthTokenResource($token);
    }
}
