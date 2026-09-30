<?php

namespace App\Support;

use App\Enums\ContactType;

/**
 * Canonical form of the contact details of an account (pure logic, no framework dependency).
 *
 * Emails are trimmed and ASCII-lower-cased. Phones are reduced to a simple E.164 shape: "+"
 * followed by digits only. A phone written without "+" or "00" is considered national and
 * receives the default country code. Validity is not enforced here: validation rules must reject
 * values for which {@see self::isValidPhone()} or {@see self::isAsciiEmail()} is false.
 */
final class ContactNormalizer
{
    /**
     * Normalize an email address; a blank value becomes null.
     *
     * Only ASCII letters are lower-cased (`strtolower`, locale-independent since PHP 8), exactly
     * like PostgreSQL's `LOWER()` on ASCII, which backs the case-insensitive unique index on
     * `users.email`. Emails with non-ASCII characters are refused by validation (see
     * {@see self::isAsciiEmail()}): Unicode case folding differs between PHP and the database
     * collation, so two spellings of one address could otherwise map to two accounts.
     */
    public static function email(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /**
     * Normalize a phone number to "+<digits>"; a blank value becomes null.
     *
     * @param  string  $defaultCountryCode  Digits only, without "+" (for example "222").
     */
    public static function phone(?string $phone, string $defaultCountryCode = '222'): ?string
    {
        $compact = preg_replace('/[\s.\-()\x{00A0}]+/u', '', (string) $phone) ?? '';
        $digits = preg_replace('/\D+/', '', $compact) ?? '';

        if ($digits === '') {
            return null;
        }

        $international = str_starts_with($compact, '+') || str_starts_with($compact, '00');

        if (str_starts_with($compact, '00')) {
            $digits = substr($digits, 2);
        }

        return $international
            ? '+'.$digits
            : '+'.preg_replace('/\D+/', '', $defaultCountryCode).$digits;
    }

    /**
     * Normalize a raw contact whose kind is not known yet (login or OTP form field).
     */
    public static function normalize(string $contact, string $defaultCountryCode = '222'): ?string
    {
        return match (ContactType::detect($contact)) {
            ContactType::Email => self::email($contact),
            ContactType::Phone => self::phone($contact, $defaultCountryCode),
        };
    }

    /**
     * Whether the (trimmed) email is made of printable ASCII characters only.
     */
    public static function isAsciiEmail(?string $email): bool
    {
        return $email !== null && preg_match('/\A[\x21-\x7E]+\z/', trim($email)) === 1;
    }

    /**
     * Whether the value is a well-formed E.164 number (at most 15 digits, no leading zero).
     */
    public static function isValidPhone(?string $phone): bool
    {
        return $phone !== null && preg_match('/^\+[1-9]\d{6,14}$/', $phone) === 1;
    }
}
