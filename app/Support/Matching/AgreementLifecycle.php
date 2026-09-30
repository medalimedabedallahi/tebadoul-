<?php

namespace App\Support\Matching;

use App\Enums\ContactEventAction;
use App\Enums\MobilityRequestStatus;
use App\Enums\RequestCloseReason;
use App\Jobs\RecomputeMatches;
use App\Models\ContactEvent;
use App\Models\MatchParticipant;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;

/**
 * What an agreement does to the requests taking part in it. Call inside the transaction that
 * changes the match status, with the match row locked.
 *
 * - start: both requests become `matched`; their other matches are then invalidated by the
 *   recomputation (one agreement at a time per request);
 * - end: the requests are released (published again, or expired if their date has passed),
 *   or closed as `permutation_completed` when the permutation was approved. Every contact
 *   consent is revoked, and audited as such: future access ends with the agreement.
 */
final class AgreementLifecycle
{
    public function start(MobilityMatch $match): void
    {
        foreach ($this->lockedRequests($match) as $request) {
            $request->transitionTo(MobilityRequestStatus::Matched, 'match');
            $request->save();
            RecomputeMatches::forRequest($request);
        }
    }

    public function end(MobilityMatch $match, bool $permutationCompleted = false): void
    {
        foreach ($match->participants()->whereNotNull('contact_consented_at')->get() as $participant) {
            /** @var MatchParticipant $participant */
            $participant->forceFill(['contact_consented_at' => null])->save();
            ContactEvent::record($match, $participant->user, ContactEventAction::Revoked, null);
        }

        foreach ($this->lockedRequests($match) as $request) {
            if ($request->status !== MobilityRequestStatus::Matched) {
                continue;
            }

            if ($permutationCompleted) {
                $request->transitionTo(MobilityRequestStatus::Closed, 'close');
                $request->close_reason = RequestCloseReason::PermutationCompleted;
            } else {
                $request->transitionTo(
                    $request->expires_at->isBefore(today()) ? MobilityRequestStatus::Expired : MobilityRequestStatus::Published,
                    'release',
                );
            }

            $request->save();
            RecomputeMatches::forRequest($request);
        }
    }

    /**
     * @return iterable<MobilityRequest>
     */
    private function lockedRequests(MobilityMatch $match): iterable
    {
        return MobilityRequest::withTrashed()
            ->whereIn('id', $match->participants()->select('mobility_request_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
