<?php

namespace App\Exceptions\References;

use Exception;

/**
 * A reference CSV file was refused. Nothing was written: the import is all or nothing.
 */
class InvalidReferenceImportException extends Exception
{
    /**
     * @param  list<string>  $errors  One human-readable message per problem, with its line number.
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The reference file is invalid: '.implode(' ', $errors));
    }
}
