<?php

namespace App\Support;

use RuntimeException;

/**
 * Purpose-specific secret keys derived from `app.key` (domain separation).
 *
 * Every keyed hash of the application uses its own key: a value computed for one purpose (a
 * one-time code hash, a rate-limiter key, an idempotency fingerprint) can never be reused or
 * compared across purposes, and a leak of one kind of hash tells nothing about the others. Each key
 * is `HMAC-SHA256(app.key, "badal:<purpose>")`, so rotating `app.key` rotates them all.
 */
final class DerivedKey
{
    /**
     * Keyed hash of one-time codes ({@see VerificationCode}).
     */
    public const ONE_TIME_CODE = 'otp-code:v1';

    /**
     * Keyed hash of the contacts used in rate-limiter keys.
     */
    public const RATE_LIMITER = 'rate-limiter:v1';

    /**
     * Keyed hash of an idempotency key and of the request it covers.
     */
    public const IDEMPOTENCY = 'idempotency:v1';

    /**
     * The binary key of `$purpose`.
     *
     * @throws RuntimeException When `app.key` is not configured.
     */
    public static function for(string $purpose): string
    {
        $appKey = (string) config('app.key');

        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is not set: no derived key can be computed.');
        }

        return hash_hmac('sha256', 'badal:'.$purpose, $appKey, true);
    }

    /**
     * Hex HMAC-SHA256 of `$message` under the key of `$purpose`.
     */
    public static function hmac(string $purpose, string $message): string
    {
        return hash_hmac('sha256', $message, self::for($purpose));
    }
}
