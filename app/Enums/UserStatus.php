<?php

namespace App\Enums;

/**
 * Lifecycle state of a user account.
 */
enum UserStatus: string
{
    case Active = 'active';
    case PendingVerification = 'pending_verification';
    case Suspended = 'suspended';

    /**
     * Deleted by its owner: anonymized (no contact, no name) and kept only for the audit trail, the
     * reports and the other participants' conversations. Final: never reactivated.
     */
    case Deleted = 'deleted';

    /**
     * Whether the account may sign in and use the platform.
     */
    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
