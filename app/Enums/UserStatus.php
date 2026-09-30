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
     * Whether the account may sign in and use the platform.
     */
    public function canSignIn(): bool
    {
        return $this === self::Active;
    }
}
