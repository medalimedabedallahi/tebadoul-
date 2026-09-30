<?php

namespace App\Actions\Matching;

use App\Actions\Matching\Concerns\ManagesParticipation;
use App\Enums\MatchDecision;
use App\Enums\MatchDeclineReason;
use App\Enums\MatchStatus;
use App\Enums\NotificationType;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Models\MobilityMatch;
use App\Models\User;
use App\Support\Notifications\UserNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Declines a match, with an optional reason from {@see MatchDeclineReason}: either participant
 * can dismiss a suggestion, and the invited participant can refuse an invitation. `declined` is
 * final: the pair is never suggested again.
 */
final class DeclineMatch
{
    use ManagesParticipation;

    public function __construct(private readonly UserNotifier $notifier) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['nullable', Rule::enum(MatchDeclineReason::class)],
        ];
    }

    /**
     * @throws InvalidMatchTransitionException
     */
    public function handle(User $user, string $publicId, ?MatchDeclineReason $reason = null): MobilityMatch
    {
        return DB::transaction(function () use ($user, $publicId, $reason): MobilityMatch {
            [$match, $mine, $other] = $this->lockParticipation($user, $publicId);

            $canDecline = $match->status === MatchStatus::Suggested
                || ($match->status === MatchStatus::Invited && $mine->decision === null);

            if (! $canDecline) {
                throw new InvalidMatchTransitionException($match->status, 'decline');
            }

            $wasInvitation = $match->status === MatchStatus::Invited;
            $mine->forceFill(['decision' => MatchDecision::Declined, 'decided_at' => now()])->save();

            $match->status = MatchStatus::Declined;
            $match->outcome_reason = $reason?->value;
            $match->expires_at = null;
            $match->save();

            // Dismissing a mere suggestion is not worth telling; refusing an invitation is.
            if ($wasInvitation) {
                $this->notifier->notify($other->user, NotificationType::InvitationDeclined, $match);
            }

            return $match;
        });
    }
}
