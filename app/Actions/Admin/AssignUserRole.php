<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Admin\RoleNotAssignableException;
use App\Models\User;
use App\Support\ContactNormalizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Gives a role to an existing account, found by its email address.
 *
 * Server-side only (`auth:assign-role`, run in the `app` container): it is how the first
 * administrator of an environment is named, so it has no actor and no HTTP entry point. The person
 * registers through the site and verifies the email first; the role never activates an account.
 * Idempotent.
 */
final class AssignUserRole
{
    /**
     * @throws ModelNotFoundException
     * @throws RoleNotAssignableException
     */
    public function handle(string $email, UserRole $role): User
    {
        return DB::transaction(function () use ($email, $role): User {
            $user = User::query()
                ->where('email', ContactNormalizer::email($email))
                ->lockForUpdate()
                ->firstOrFail();

            if ($user->status !== UserStatus::Active || $user->email_verified_at === null) {
                throw new RoleNotAssignableException;
            }

            $user->role = $role;
            $user->save();

            return $user;
        });
    }
}
