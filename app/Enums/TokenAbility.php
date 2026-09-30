<?php

namespace App\Enums;

use App\Actions\Auth\LoginUser;

/**
 * Abilities of the Sanctum personal access tokens issued by {@see LoginUser}.
 *
 * Only active accounts receive a token, with `access-api`. `verify-contact` was the limited
 * ability of an account pending verification; such an account can no longer sign in, so it is no
 * longer issued. It is kept so that a token issued before that change stays readable (and is
 * refused anyway by the `account.active` middleware while the account is not active).
 */
enum TokenAbility: string
{
    case AccessApi = 'access-api';
    case VerifyContact = 'verify-contact';
}
