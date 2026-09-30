<?php

namespace App\Actions\MobilityRequests;

use App\Actions\MobilityRequests\Concerns\ManagesOwnRequest;
use App\Enums\MobilityRequestStatus;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Jobs\RecomputeMatches;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Soft-deletes a request of the owner, when {@see MobilityRequestStatus::isDeletable()}: a
 * published or matched request must be paused or closed first.
 */
final class DeleteMobilityRequest
{
    use ManagesOwnRequest;

    /**
     * @throws InvalidRequestTransitionException
     */
    public function handle(User $user, string $publicId): void
    {
        DB::transaction(function () use ($user, $publicId): void {
            $request = $this->lockOwnRequest($user, $publicId);
            Gate::forUser($user)->authorize('delete', $request);

            if (! $request->status->isDeletable()) {
                throw new InvalidRequestTransitionException($request->status, 'delete');
            }

            $request->delete();
            RecomputeMatches::forRequest($request);
        });
    }
}
