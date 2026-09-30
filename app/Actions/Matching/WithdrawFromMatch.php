<?php

namespace App\Actions\Matching;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\MatchDecision;
use App\Enums\MatchStatus;
use App\Enums\NotificationType;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Models\MobilityMatch;
use App\Models\User;
use App\Support\Notifications\UserNotifier;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws from a match: the inviter cancels a pending invitation, or either participant
 * abandons an agreement in progress (PRD 5.2 "abandonnee"). An abandoned agreement releases
 * both requests and ends every contact consent. `withdrawn` is final.
 */
final class WithdrawFromMatch
{
    use ManagesParticipation;

    public function __construct(private readonly UserNotifier $notifier) {}

    /**
     * @throws InvalidMatchTransitionException
     */
    public function handle(User $user, string $publicId): MobilityMatch
    {
        return DB::transaction(function () use ($user, $publicId): MobilityMatch {
            [$match, $mine, $other] = $this->lockParticipation($user, $publicId);

            $wasAccord = $match->status->isAccord();
            $isInviter = $match->status === MatchStatus::Invited && $mine->decision === MatchDecision::Accepted;

            if (! $wasAccord && ! $isInviter) {
                throw new InvalidMatchTransitionException($match->status, 'withdraw');
            }

            $mine->forceFill(['decision' => MatchDecision::Withdrawn, 'decided_at' => now()])->save();

            $match->status = MatchStatus::Withdrawn;
            $match->expires_at = null;
            $match->save();

            if ($wasAccord) {
                $this->agreements()->end($match);
            }

            $this->notifier->notify($other->user, NotificationType::MatchWithdrawn, $match);

            return $match;
        });
    }
}
