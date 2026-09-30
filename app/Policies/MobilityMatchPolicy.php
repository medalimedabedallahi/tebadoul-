<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\MobilityMatch;
use App\Models\User;

/**
 * A match is only visible to its participants, with an active account. The lookups are scoped
 * to the participant's matches (404 otherwise); this policy is the explicit second check.
 */
class MobilityMatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }

    public function view(User $user, MobilityMatch $match): bool
    {
        return $user->status === UserStatus::Active
            && $match->participants->contains('user_id', $user->getKey());
    }
}
