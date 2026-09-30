<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The one-time code cannot be used: wrong, expired, already used, locked after too many attempts,
 * or no code was ever issued for this contact. The cases are deliberately indistinguishable.
 */
class InvalidVerificationCodeException extends Exception implements ShouldntReport
{
    //
}
