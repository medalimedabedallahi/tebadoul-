<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MobilityRequests\DeleteMobilityRequest;
use App\Actions\MobilityRequests\ListMobilityRequests;
use App\Actions\MobilityRequests\SaveMobilityRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobilityRequests\ListMobilityRequestsRequest;
use App\Http\Requests\Api\V1\MobilityRequests\StoreMobilityRequestRequest;
use App\Http\Requests\Api\V1\MobilityRequests\UpdateMobilityRequestRequest;
use App\Http\Resources\MobilityRequestResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The authenticated account's own mobility requests (PRD section 10.4). A request of another
 * account is answered like an unknown one (404).
 */
class MobilityRequestController extends Controller
{
    public function index(ListMobilityRequestsRequest $request, ListMobilityRequests $listRequests): AnonymousResourceCollection
    {
        return MobilityRequestResource::collection($listRequests->handle(
            user: $this->user($request),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 20),
        ));
    }

    public function store(StoreMobilityRequestRequest $request, SaveMobilityRequest $saveRequest): JsonResponse
    {
        return (new MobilityRequestResource($saveRequest->handle($this->user($request), $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $mobilityRequest): MobilityRequestResource
    {
        $found = $this->user($request)->mobilityRequests()
            ->where('public_id', Str::lower($mobilityRequest))
            ->with(['destinations.wilaya', 'destinations.moughataa', 'destinations.establishment'])
            ->firstOrFail();

        Gate::authorize('view', $found);

        return new MobilityRequestResource($found);
    }

    public function update(UpdateMobilityRequestRequest $request, string $mobilityRequest, SaveMobilityRequest $saveRequest): MobilityRequestResource
    {
        return new MobilityRequestResource($saveRequest->handle($this->user($request), $request->validated(), $mobilityRequest));
    }

    public function destroy(Request $request, string $mobilityRequest, DeleteMobilityRequest $deleteRequest): Response
    {
        $deleteRequest->handle($this->user($request), $mobilityRequest);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
