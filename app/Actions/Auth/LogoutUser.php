<?php

namespace App\Actions\Auth;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * Signs out by revoking the token that authenticated the current request. Other tokens of the
 * account (other devices) stay valid. A request authenticated by the session (first-party SPA)
 * has no personal access token: there is nothing to revoke here.
 */
final class LogoutUser
{
    public function handle(User $user): void
    {
        /** @var PersonalAccessToken|TransientToken|null $token */
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
