<?php

namespace App\Rules;

use App\Support\ContactNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An email address written with printable ASCII characters only. Internationalized addresses are
 * refused: see {@see ContactNormalizer::email()} for why (case folding must match the database).
 */
class AsciiEmail implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ContactNormalizer::isAsciiEmail($value)) {
            $fail('The :attribute field must only contain unaccented letters, digits and symbols.')->translate();
        }
    }
}
