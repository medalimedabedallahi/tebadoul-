<?php

namespace App\Exceptions\MobilityRequests;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;

/**
 * The mobility request cannot be published (or renewed) as it is: missing professional profile,
 * inconsistent destination, expiry date passed, or another active request (FR 007). Rendered as
 * `409 request_not_publishable`, with the reasons keyed by field.
 */
class RequestNotPublishableException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The mobility request cannot be published.');
    }
}
