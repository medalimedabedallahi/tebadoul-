<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\MobilityRequest;
use App\Models\User;

/**
 * A mobility request is only ever managed by its owner, with an active account. Other accounts
 * never reach it: the actions look it up among the actor's own requests (404 otherwise), and
 * this policy is the second, explicit check.
 */
class MobilityRequestPolicy
{
    public function create(User $user): bool
    {
        return $user->status === UserStatus::Active;
    }

    public function view(User $user, MobilityRequest $mobilityRequest): bool
    {
        return $this->owns($user, $mobilityRequest);
    }

    public function update(User $user, MobilityRequest $mobilityRequest): bool
    {
        return $this->owns($user, $mobilityRequest);
    }

    public function delete(User $user, MobilityRequest $mobilityRequest): bool
    {
        return $this->owns($user, $mobilityRequest);
    }

    private function owns(User $user, MobilityRequest $mobilityRequest): bool
    {
        return $user->status === UserStatus::Active && $mobilityRequest->user_id === $user->getKey();
    }
}
