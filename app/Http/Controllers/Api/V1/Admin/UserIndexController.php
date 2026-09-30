<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\ListUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListUsersRequest;
use App\Http\Resources\UserModerationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserIndexController extends Controller
{
    public function __invoke(ListUsersRequest $request, ListUsers $listUsers): AnonymousResourceCollection
    {
        $publicId = $request->validated('public_id');

        return UserModerationResource::collection($listUsers->handle(
            actor: $request->user(),
            publicId: is_string($publicId) ? $publicId : null,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 20),
        ));
    }
}
