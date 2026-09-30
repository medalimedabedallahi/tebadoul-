<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Auth\SuspendUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserSuspensionRequest;
use App\Http\Resources\UserModerationResource;

class UserSuspensionController extends Controller
{
    public function __invoke(
        StoreUserSuspensionRequest $request,
        string $userPublicId,
        SuspendUser $suspendUser,
    ): UserModerationResource {
        $user = $suspendUser->handle(
            actor: $request->user(),
            targetPublicId: $userPublicId,
            reason: $request->string('reason')->trim()->toString(),
            ipAddress: $request->ip(),
        );

        return new UserModerationResource($user);
    }
}
