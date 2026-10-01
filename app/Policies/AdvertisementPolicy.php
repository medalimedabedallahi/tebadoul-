<?php

namespace App\Policies;

use App\Actions\Advertising\PickAdvertisement;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Advertisement;
use App\Models\User;

/**
 * Advertisements are managed by active administrators only. Showing them needs no permission:
 * {@see PickAdvertisement} only returns displayable ones.
 */
class AdvertisementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->role === UserRole::Administrator;
    }

    public function view(User $user, Advertisement $advertisement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Advertisement $advertisement): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Advertisement $advertisement): bool
    {
        return $this->viewAny($user);
    }
}
