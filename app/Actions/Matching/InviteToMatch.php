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
 * Sends an invitation on a suggested match (PRD 5.2, step 2): inviting counts as accepting.
 * The other participant has `matching.invitation_ttl_days` days to answer. No contact detail
 * is revealed by an invitation.
 */
final class InviteToMatch
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

            if ($match->status !== MatchStatus::Suggested || ! $requestsPublished) {
                throw new InvalidMatchTransitionException($match->status, 'invite');
            }

            $mine->forceFill(['decision' => MatchDecision::Accepted, 'decided_at' => now()])->save();

            $match->status = MatchStatus::Invited;
            $match->expires_at = now()->addDays(max(1, (int) config('matching.invitation_ttl_days', 14)));
            $match->save();

            $this->notifier->notify($other->user, NotificationType::InvitationReceived, $match);

            return $match;
        });
    }
}
