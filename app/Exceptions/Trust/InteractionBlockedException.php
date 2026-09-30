<?php

namespace App\Exceptions\Trust;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

class InteractionBlockedException extends Exception implements ShouldntReport
{
    // Expected trust boundary: never reveal which participant created the block.
}
