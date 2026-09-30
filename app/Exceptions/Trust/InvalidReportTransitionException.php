<?php

namespace App\Exceptions\Trust;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class InvalidReportTransitionException extends Exception implements ShouldntReport
{
    // A resolved or dismissed report cannot be decided again.
}
