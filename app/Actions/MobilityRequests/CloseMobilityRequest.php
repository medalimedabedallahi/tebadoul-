<?php

namespace App\Actions\MobilityRequests;

use App\Actions\MobilityRequests\Concerns\ManagesOwnRequest;
use App\Enums\MobilityRequestStatus;
use App\Enums\RequestCloseReason;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Jobs\RecomputeMatches;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Ends a request for good, with a reason from {@see RequestCloseReason}. A closed request is
 * final (it can still be deleted). A draft is deleted rather than closed.
 */
final class CloseMobilityRequest
{
    use ManagesOwnRequest;

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(RequestCloseReason::class)],
        ];
    }

    /**
     * @throws InvalidRequestTransitionException
     */
    public function handle(User $user, string $publicId, RequestCloseReason $reason): MobilityRequest
    {
        return DB::transaction(function () use ($user, $publicId, $reason): MobilityRequest {
            $request = $this->lockOwnRequest($user, $publicId);

            $request->transitionTo(MobilityRequestStatus::Closed, 'close');
            $request->close_reason = $reason;
            $request->save();
            RecomputeMatches::forRequest($request);

            return $request;
        });
    }
}
