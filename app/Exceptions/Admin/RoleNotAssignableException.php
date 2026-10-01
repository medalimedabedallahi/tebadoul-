<?php

namespace App\Exceptions\Admin;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * A role can only be given to an active account whose email address has been verified: a role
 * never makes a pending, suspended or deleted account usable.
 */
class RoleNotAssignableException extends Exception implements ShouldntReport
{
    //
}
