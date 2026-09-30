<?php

namespace App\Exceptions\Matching;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The other participant's contact details were requested without an agreement in progress
 * where both participants consent to share them (ADR 0001, decisions 5 and 6). Rendered as
 * `403 contact_not_authorized`.
 */
class ContactNotAuthorizedException extends Exception implements ShouldntReport
{
    //
}
