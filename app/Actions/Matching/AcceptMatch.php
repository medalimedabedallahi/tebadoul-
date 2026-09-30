<?php

namespace App\Actions\Matching;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\MatchDecision;
use App\Enums\MatchStatus;
use App\Enums\MobilityRequestStatus;
use App\Enums\NotificationType;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Models\MobilityMatch;
use App\Models\MobilityRequest;
use App\Models\User;
use App\Support\Notifications\UserNotifier;
use App\Support\Trust\BlockGuard;
use Illuminate\Support\Facades\DB;

/**
 * The invited participant accepts (PRD 5.2, step 3): the match becomes `mutual`, both requests
 * become `matched`, and their other matches are withdrawn by the recomputation. Accepting
 * never reveals contact details (ADR 0001, decision 5): each participant consents separately.
 */
final class AcceptMatch
{
    use ManagesParticipation;

    public function __construct(
        private readonly BlockGuard $blockGuard,
        private readonly UserNotifier $notifier,
    ) {}

    /**
     * @throws InvalidMatchTransitionException
     */
    public function handle(User $user, string $publicId): MobilityMatch
    {
        return DB::transaction(function () use ($user, $publicId): MobilityMatch {
            [$match, $mine, $other] = $this->lockParticipation($user, $publicId);
            $this->blockGuard->ensureInteractionAllowed($user, $other->user);

            $requestsPublished = MobilityRequest::query()
                ->whereIn('id', $match->participants->pluck('mobility_request_id'))
                ->where('status', MobilityRequestStatus::Published)
                ->count() === 2;

            if ($match->status !== MatchStatus::Invited || $mine->decision !== null || ! $requestsPublished) {
                throw new InvalidMatchTransitionException($match->status, 'accept');
            }

            $mine->forceFill(['decision' => MatchDecision::Accepted, 'decided_at' => now()])->save();

            $match->status = MatchStatus::Mutual;
            $match->expires_at = null;
            $match->save();

            $this->agreements()->start($match);
            $this->notifier->notify($other->user, NotificationType::MatchAccepted, $match);

            return $match;
        });
    }
}
