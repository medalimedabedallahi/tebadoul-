<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The credentials are correct but the account is suspended. Only raised after the password has
 * been verified, so it reveals nothing to someone who does not know the password.
 */
class AccountSuspendedException extends Exception implements ShouldntReport
{
    //
}
