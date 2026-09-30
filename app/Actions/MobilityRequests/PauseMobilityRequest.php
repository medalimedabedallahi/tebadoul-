<?php

namespace App\Actions\MobilityRequests;

use App\Actions\MobilityRequests\Concerns\ManagesOwnRequest;
use App\Enums\MobilityRequestStatus;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Jobs\RecomputeMatches;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Temporarily hides a published request from matching. It still counts as the account's active
 * request and is resumed with {@see PublishMobilityRequest}.
 */
final class PauseMobilityRequest
{
    use ManagesOwnRequest;

    /**
     * @throws InvalidRequestTransitionException
     */
    public function handle(User $user, string $publicId): MobilityRequest
    {
        return DB::transaction(function () use ($user, $publicId): MobilityRequest {
            $request = $this->lockOwnRequest($user, $publicId);

            if ($request->status !== MobilityRequestStatus::Published) {
                throw new InvalidRequestTransitionException($request->status, 'pause');
            }

            $request->transitionTo(MobilityRequestStatus::Paused, 'pause');
            $request->save();
            RecomputeMatches::forRequest($request);

            return $request;
        });
    }
}
