<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MobilityRequests\CloseMobilityRequest;
use App\Actions\MobilityRequests\PauseMobilityRequest;
use App\Actions\MobilityRequests\PublishMobilityRequest;
use App\Actions\MobilityRequests\RenewMobilityRequest;
use App\Enums\RequestCloseReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobilityRequests\CloseMobilityRequestRequest;
use App\Http\Requests\Api\V1\MobilityRequests\RenewMobilityRequestRequest;
use App\Http\Resources\MobilityRequestResource;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Lifecycle operations of a mobility request. An operation the current status does not allow
 * is answered `409 invalid_request_transition`; a publication that is not possible,
 * `409 request_not_publishable` with the reasons.
 */
class MobilityRequestStatusController extends Controller
{
    public function publish(Request $request, string $mobilityRequest, PublishMobilityRequest $publish): MobilityRequestResource
    {
        return $this->present($publish->handle($this->user($request), $mobilityRequest));
    }

    public function pause(Request $request, string $mobilityRequest, PauseMobilityRequest $pause): MobilityRequestResource
    {
        return $this->present($pause->handle($this->user($request), $mobilityRequest));
    }

    public function renew(RenewMobilityRequestRequest $request, string $mobilityRequest, RenewMobilityRequest $renew): MobilityRequestResource
    {
        return $this->present($renew->handle($this->user($request), $mobilityRequest, $request->string('expires_at')->toString()));
    }

    public function close(CloseMobilityRequestRequest $request, string $mobilityRequest, CloseMobilityRequest $close): MobilityRequestResource
    {
        return $this->present($close->handle(
            $this->user($request),
            $mobilityRequest,
            $request->enum('reason', RequestCloseReason::class) ?? RequestCloseReason::Other,
        ));
    }

    private function present(MobilityRequest $mobilityRequest): MobilityRequestResource
    {
        return new MobilityRequestResource($mobilityRequest->load(['destinations.wilaya', 'destinations.moughataa', 'destinations.establishment']));
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
