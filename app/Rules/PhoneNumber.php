<?php

namespace App\Rules;

use App\Support\ContactNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A phone number that, once normalized (see {@see ContactNormalizer::phone()}), is a well-formed
 * E.164 number. A number without international prefix receives `app.default_phone_country_code`.
 */
class PhoneNumber implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ContactNormalizer::isValidPhone(
            ContactNormalizer::phone($value, (string) config('app.default_phone_country_code', '222'))
        )) {
            $fail('The :attribute field must be a valid phone number.')->translate();
        }
    }
}
