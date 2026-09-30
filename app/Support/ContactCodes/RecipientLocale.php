<?php

namespace App\Support\ContactCodes;

use App\Models\ContactVerification;
use App\Models\User;

/**
 * Language of the message sent to the owner of a code: their profile locale when it is supported,
 * the application locale otherwise.
 */
final class RecipientLocale
{
    public static function of(ContactVerification $verification): string
    {
        return self::forUser($verification->user);
    }

    public static function forUser(?User $user): string
    {
        $locale = $user?->locale;

        return is_string($locale) && in_array($locale, (array) config('app.supported_locales'), true)
            ? $locale
            : (string) config('app.locale');
    }
}
