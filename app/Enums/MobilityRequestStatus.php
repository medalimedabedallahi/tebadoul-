<?php

namespace App\Enums;

/**
 * Lifecycle of a mobility request (PRD section 8.1). Deletion is a soft delete, not a status.
 *
 * `matched` is only entered by the matching engine; users never set it themselves.
 */
enum MobilityRequestStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
    case Matched = 'matched';
    case Expired = 'expired';
    case Closed = 'closed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Published],
            self::Published => [self::Paused, self::Matched, self::Expired, self::Closed],
            self::Paused => [self::Published, self::Expired, self::Closed],
            self::Matched => [self::Published, self::Expired, self::Closed],
            self::Expired => [self::Published, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    /**
     * Counts toward the "one active request per account" rule (FR 007).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::Published, self::Paused, self::Matched], true);
    }

    /**
     * @return list<self>
     */
    public static function activeStatuses(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isActive()));
    }

    /**
     * Destinations and dates can still be changed. A matched or closed request is frozen.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Published, self::Paused, self::Expired], true);
    }

    /**
     * Can be soft-deleted by its owner. A published or matched request must be paused or closed
     * first, so that nobody engaged with it sees it vanish silently.
     */
    public function isDeletable(): bool
    {
        return in_array($this, [self::Draft, self::Paused, self::Expired, self::Closed], true);
    }
}
