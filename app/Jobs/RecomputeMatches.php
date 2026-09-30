<?php

namespace App\Jobs;

use App\Actions\Matching\ComputeDirectMatches;
use App\Enums\MobilityRequestStatus;
use App\Models\MobilityRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes the matches of one mobility request, on the `matching` queue, once the
 * transaction that changed it has committed (PRD 7.5: recalcul asynchrone).
 *
 * Unique until processing: a burst of changes to the same request collapses into one pending
 * job. {@see ComputeDirectMatches} is idempotent, so a duplicate run is harmless.
 */
class RecomputeMatches implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 50;

    public function __construct(public readonly int $mobilityRequestId)
    {
        $this->onQueue('matching');
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->mobilityRequestId;
    }

    public function handle(ComputeDirectMatches $computeMatches): void
    {
        $request = MobilityRequest::withTrashed()->find($this->mobilityRequestId);

        if ($request !== null) {
            $computeMatches->handle($request);
        }
    }

    /**
     * Queues a recomputation for the request, when matching is enabled.
     */
    public static function forRequest(MobilityRequest $request): void
    {
        if (config('matching.enabled')) {
            self::dispatch($request->id);
        }
    }

    /**
     * Queues a recomputation for every request of `$user` that can take part in a match
     * (after a profile change, a suspension or a reinstatement).
     */
    public static function forUser(User $user): void
    {
        if (! config('matching.enabled')) {
            return;
        }

        $user->mobilityRequests()
            ->whereIn('status', [...MobilityRequestStatus::activeStatuses(), MobilityRequestStatus::Expired])
            ->pluck('id')
            ->each(fn (int $id) => self::dispatch($id));
    }
}
