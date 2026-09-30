<?php

namespace App\Exceptions\Admin;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * A reinstatement targets an account that is not suspended. Only raised once the actor has been
 * authorized to manage the account, so it reveals nothing to an unauthorized caller.
 */
class AccountNotSuspendedException extends Exception implements ShouldntReport
{
    //
}
