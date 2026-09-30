<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The identifier and password do not match an account. Deliberately identical for an unknown
 * identifier and for a wrong password, so that the response never reveals whether an account
 * exists. Expected outcome of normal use: never reported to the logs.
 */
class InvalidCredentialsException extends Exception implements ShouldntReport
{
    //
}
