<?php

namespace App\Rules;

use App\Enums\ContactType;
use App\Support\ContactNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Either an email address or a phone number (a value containing "@" is treated as an email).
 * Used by the fields that designate an account without saying which kind of contact it is.
 */
class ContactIdentifier implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && $this->isValid($value)) {
            return;
        }

        $fail('The :attribute field must be a valid email address or phone number.')->translate();
    }

    private function isValid(string $value): bool
    {
        return match (ContactType::detect($value)) {
            ContactType::Email => ContactNormalizer::isAsciiEmail($value) && Validator::make(
                ['email' => ContactNormalizer::email($value)],
                ['email' => ['required', 'email:rfc', 'max:255']],
            )->passes(),
            ContactType::Phone => ContactNormalizer::isValidPhone(
                ContactNormalizer::phone($value, (string) config('app.default_phone_country_code', '222'))
            ),
        };
    }
}
