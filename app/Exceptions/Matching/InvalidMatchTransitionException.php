<?php

namespace App\Exceptions\Matching;

use App\Enums\MatchStatus;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The operation is not allowed in the current status of the match, or not for this
 * participant (for example accepting one's own invitation). Rendered as
 * `409 invalid_match_transition`.
 */
class InvalidMatchTransitionException extends Exception implements ShouldntReport
{
    public function __construct(public readonly MatchStatus $from, public readonly string $operation)
    {
        parent::__construct("Cannot {$operation} a match in status {$from->value}.");
    }
}
