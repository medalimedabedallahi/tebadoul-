<?php

namespace App\Exceptions\Trust;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class ReportAlreadyExistsException extends Exception implements ShouldntReport
{
    // One report per participant and match prevents duplicate moderation work.
}
