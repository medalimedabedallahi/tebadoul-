<?php

namespace App\Support;

use App\Enums\ContactPurpose;

/**
 * Generation and keyed hashing of one-time numeric codes.
 *
 * A code has too little entropy to be stored with a plain hash (1 million possibilities for six
 * digits), so it is hashed with HMAC-SHA256 under a key derived from the application key for this
 * purpose only ({@see DerivedKey::ONE_TIME_CODE}): a copy of the database alone does not allow the
 * codes to be recovered. The contact and the purpose are part of the hashed message, so a hash
 * cannot be replayed for another contact or another use.
 */
final class VerificationCode
{
    /**
     * A uniformly random code of `$length` digits (leading zeros kept), drawn with random_int().
     */
    public static function generate(int $length): string
    {
        $length = max(4, min(9, $length));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Hash a code for a normalized contact and a purpose.
     */
    public static function hash(string $contact, ContactPurpose $purpose, string $code): string
    {
        return DerivedKey::hmac(DerivedKey::ONE_TIME_CODE, $contact."\n".$purpose->value."\n".$code);
    }

    /**
     * Compare a submitted code with a stored hash in constant time.
     */
    public static function matches(string $storedHash, string $contact, ContactPurpose $purpose, string $submittedCode): bool
    {
        return hash_equals($storedHash, self::hash($contact, $purpose, $submittedCode));
    }
}
