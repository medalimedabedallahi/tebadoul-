<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Replaces an exception raised inside a queued job whose message may contain personal data (a
 * mail or SMS transport error naming the recipient, a query error carrying its bindings).
 *
 * Only the class and the code of the original exception are kept; its message and its trace (whose
 * frames can carry the recipient as an argument) are dropped, and it is NOT chained as `previous`,
 * so neither the exception handler nor the `failed_jobs` table ever receives them.
 */
final class RedactedJobException extends RuntimeException
{
    /**
     * @param  class-string<Throwable>  $originalClass
     */
    public function __construct(
        public readonly string $originalClass,
        public readonly string $originalCode,
        string $operation,
    ) {
        parent::__construct(sprintf(
            '%s failed with %s (code %s). Details withheld: they may contain personal data.',
            $operation,
            $originalClass,
            $originalCode,
        ));
    }

    public static function from(Throwable $exception, string $operation): self
    {
        return new self($exception::class, (string) $exception->getCode(), $operation);
    }
}
