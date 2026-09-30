<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;

/**
 * The audit trail is read by active administrators only, and never changed by anyone: entries
 * are written by the audited actions themselves.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->role === UserRole::Administrator;
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
