<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Matching\ListMatches;
use App\Enums\MatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Matches\ListMatchesRequest;
use App\Http\Resources\MobilityMatchResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The authenticated account's matches, with their score and its explanation (PRD 10.5).
 */
class MatchController extends Controller
{
    public function index(ListMatchesRequest $request, ListMatches $listMatches): AnonymousResourceCollection
    {
        $requestPublicId = $request->validated('mobility_request');

        return MobilityMatchResource::collection($listMatches->handle(
            user: $this->user($request),
            status: $request->enum('status', MatchStatus::class),
            requestPublicId: is_string($requestPublicId) ? $requestPublicId : null,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 20),
        ));
    }

    public function show(Request $request, string $match, ListMatches $listMatches): MobilityMatchResource
    {
        return new MobilityMatchResource($listMatches->find($this->user($request), $match));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
