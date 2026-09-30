<?php

namespace App\Enums;

/**
 * Lifecycle of a match (PRD section 8.2). The matching engine only ever sets `suggested` and
 * `invalidated`; the other statuses are driven by the participants (invitations, consents,
 * administrative progress).
 */
enum MatchStatus: string
{
    case Suggested = 'suggested';
    case Invited = 'invited';
    case Mutual = 'mutual';
    case InDiscussion = 'in_discussion';
    case FilePrepared = 'file_prepared';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case Invalidated = 'invalidated';

    /**
     * The participants have engaged with the match: it is never removed silently, only
     * invalidated with a visible reason when its conditions stop holding.
     */
    public function isEngaged(): bool
    {
        return in_array($this, [self::Invited, self::Mutual, self::InDiscussion, self::FilePrepared, self::Submitted], true);
    }

    /**
     * A decision of the participants ended the match: the engine never changes it again.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Declined, self::Withdrawn, self::Expired], true);
    }

    /**
     * Both participants accepted: an agreement is in progress and both requests are `matched`.
     */
    public function isAccord(): bool
    {
        return in_array($this, [self::Mutual, self::InDiscussion, self::FilePrepared, self::Submitted], true);
    }

    /**
     * Administrative steps the participants can move an agreement to, from this status. Steps
     * only move forward; `approved` and `rejected` end the agreement.
     *
     * @return list<self>
     */
    public function nextSteps(): array
    {
        return match ($this) {
            self::Mutual => [self::InDiscussion, self::FilePrepared, self::Submitted],
            self::InDiscussion => [self::FilePrepared, self::Submitted],
            self::FilePrepared => [self::Submitted],
            self::Submitted => [self::Approved, self::Rejected],
            default => [],
        };
    }

    /**
     * @return list<self>
     */
    public static function reevaluable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => ! $status->isFinal()));
    }
}
