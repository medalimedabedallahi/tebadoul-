<?php

namespace App\Support;

/**
 * Masked form of a contact, safe to show to a client or to write to a log (pure logic).
 *
 * Only enough characters remain for a person to recognise their own contact: the first character
 * of the email and of its domain, the country code and the last two digits of a phone number.
 */
final class ContactMasker
{
    /**
     * "mohamed@example.org" becomes "m***@e***.org"; a value without "@" is fully masked.
     */
    public static function email(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }

        $at = strrpos($email, '@');

        if ($at === false || $at === 0 || $at === strlen($email) - 1) {
            return '***';
        }

        $domain = substr($email, $at + 1);
        $dot = strrpos($domain, '.');
        $suffix = $dot === false ? '' : substr($domain, $dot);

        return mb_substr($email, 0, 1).'***@'.mb_substr($domain, 0, 1).'***'.$suffix;
    }

    /**
     * "+22241111111" becomes "+222*****11": country code and last two digits stay visible.
     * A number that is too short to be masked meaningfully becomes "***".
     */
    public static function phone(?string $phone, string $countryCode = '222'): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) <= 4) {
            return '***';
        }

        $prefix = str_starts_with($digits, $countryCode) && strlen($digits) > strlen($countryCode) + 2
            ? '+'.$countryCode
            : '+';

        return $prefix.'*****'.substr($digits, -2);
    }

    /**
     * Mask a contact whose kind is not known: anything containing "@" is an email.
     */
    public static function mask(?string $contact, string $countryCode = '222'): ?string
    {
        if ($contact === null || $contact === '') {
            return null;
        }

        return str_contains($contact, '@') ? self::email($contact) : self::phone($contact, $countryCode);
    }
}
