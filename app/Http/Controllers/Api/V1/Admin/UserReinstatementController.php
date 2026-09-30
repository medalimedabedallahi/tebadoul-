<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Auth\ReinstateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserReinstatementRequest;
use App\Http\Resources\UserModerationResource;

class UserReinstatementController extends Controller
{
    public function __invoke(
        StoreUserReinstatementRequest $request,
        string $userPublicId,
        ReinstateUser $reinstateUser,
    ): UserModerationResource {
        $user = $reinstateUser->handle(
            actor: $request->user(),
            targetPublicId: $userPublicId,
            reason: $request->string('reason')->trim()->toString(),
            ipAddress: $request->ip(),
        );

        return new UserModerationResource($user);
    }
}
