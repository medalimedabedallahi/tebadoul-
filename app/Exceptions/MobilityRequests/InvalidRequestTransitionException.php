<?php

namespace App\Exceptions\MobilityRequests;

use App\Enums\MobilityRequestStatus;
use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The requested operation is not allowed in the current status of the mobility request (for
 * example pausing a draft, or editing a closed request). Rendered as `409 invalid_request_transition`.
 */
class InvalidRequestTransitionException extends Exception implements ShouldntReport
{
    public function __construct(public readonly MobilityRequestStatus $from, public readonly string $operation)
    {
        parent::__construct("Cannot {$operation} a mobility request in status {$from->value}.");
    }
}
