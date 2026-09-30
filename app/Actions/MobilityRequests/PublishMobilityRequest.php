<?php

namespace App\Actions\MobilityRequests;

use App\Actions\MobilityRequests\Concerns\ManagesOwnRequest;
use App\Enums\MobilityRequestStatus;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Exceptions\MobilityRequests\RequestNotPublishableException;
use App\Jobs\RecomputeMatches;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Publishes a draft, or resumes a paused request. An expired request is renewed with
 * {@see RenewMobilityRequest} instead, since it needs a new expiry date.
 */
final class PublishMobilityRequest
{
    use ManagesOwnRequest;

    /**
     * @throws InvalidRequestTransitionException
     * @throws RequestNotPublishableException
     */
    public function handle(User $user, string $publicId): MobilityRequest
    {
        return DB::transaction(function () use ($user, $publicId): MobilityRequest {
            $this->lockUser($user);
            $request = $this->lockOwnRequest($user, $publicId);

            if (! in_array($request->status, [MobilityRequestStatus::Draft, MobilityRequestStatus::Paused], true)) {
                throw new InvalidRequestTransitionException($request->status, 'publish');
            }

            $this->assertPublishable($user, $request);

            $request->transitionTo(MobilityRequestStatus::Published, 'publish');
            $request->save();
            RecomputeMatches::forRequest($request);

            return $request;
        });
    }
}
