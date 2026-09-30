<?php

namespace App\Actions\MobilityRequests;

use App\Enums\MobilityRequestStatus;
use App\Jobs\RecomputeMatches;
use App\Models\MobilityRequest;

/**
 * Scheduled every hour (PRD 12.4): published and paused requests whose expiry date has passed
 * become expired. A request is valid through its expiry date, so it expires the day after. The
 * matches of an expired request are then invalidated by {@see RecomputeMatches}.
 *
 * Each row is expired by a conditional UPDATE (still published or paused): idempotent, and safe
 * if two runs overlap or the owner acts meanwhile.
 */
final class ExpireMobilityRequests
{
    private const EXPIRABLE = [MobilityRequestStatus::Published, MobilityRequestStatus::Paused];

    public function handle(): int
    {
        $expired = 0;

        MobilityRequest::query()
            ->whereIn('status', self::EXPIRABLE)
            ->whereDate('expires_at', '<', today())
            ->select('id')
            ->chunkById(500, function ($requests) use (&$expired): void {
                foreach ($requests as $request) {
                    $updated = MobilityRequest::query()
                        ->whereKey($request->id)
                        ->whereIn('status', self::EXPIRABLE)
                        ->update(['status' => MobilityRequestStatus::Expired, 'updated_at' => now()]);

                    if ($updated === 1) {
                        $expired++;
                        RecomputeMatches::forRequest($request);
                    }
                }
            });

        return $expired;
    }
}
