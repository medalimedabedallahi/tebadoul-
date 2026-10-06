<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Accounts can only read themselves. Administrative operations use dedicated abilities and
 * minimal resources rather than granting broad access to another account (ADR 0001: contact
 * details are never exposed by default).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function linkGoogle(User $user, User $model): bool
    {
        return $user->is($model) && $user->status === UserStatus::Active;
    }

    /**
     * Only an active account manages its own professional profile.
     */
    public function manageProfessionalProfile(User $user, User $model): bool
    {
        return $user->is($model) && $user->status === UserStatus::Active;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Aggregated platform figures (PRD 15.1): counts only, never an account.
     */
    public function viewStatistics(User $actor): bool
    {
        return $actor->status === UserStatus::Active && $actor->role === UserRole::Administrator;
    }

    public function suspendAny(User $actor): bool
    {
        return $actor->role === UserRole::Administrator;
    }

    /**
     * A deleted (anonymized) account is final: suspending it would let a later reinstatement bring
     * it back.
     */
    public function suspend(User $actor, User $target): bool
    {
        return $this->suspendAny($actor)
            && $target->role === UserRole::User
            && $target->status !== UserStatus::Deleted;
    }

    /**
     * Only the owner deletes an account, never an administrator on their behalf.
     */
    public function deleteOwnAccount(User $user, User $model): bool
    {
        return $user->is($model) && $user->status === UserStatus::Active;
    }

    /**
     * Lifting a suspension follows the same rule as imposing one: only an administrator, and only
     * for an ordinary account.
     */
    public function reinstate(User $actor, User $target): bool
    {
        return $this->suspend($actor, $target);
    }
}
