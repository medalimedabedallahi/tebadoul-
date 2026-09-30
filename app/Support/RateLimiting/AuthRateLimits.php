<?php

namespace App\Support\RateLimiting;

use App\Livewire\Concerns\AppliesNamedRateLimiter;
use App\Providers\AppServiceProvider;
use App\Support\ContactNormalizer;
use App\Support\DerivedKey;
use Illuminate\Cache\RateLimiting\Limit;

/**
 * The limits of every named authentication limiter, computed from explicit values.
 *
 * This is the single definition of the throttling rules. It is called by the named limiters
 * registered in {@see AppServiceProvider::configureRateLimiting()} (API, `throttle:<name>`
 * middleware), which read ONE explicit request field each, and by
 * {@see AppliesNamedRateLimiter} (Livewire), which passes the value held by the component. Nothing
 * here reads the request: a caller cannot choose which field keys the limit by adding another one.
 *
 * - `login` (5 per minute per contact and IP, 20 per 15 minutes per contact, 30 per minute per
 *   IP): password sign-in, keyed by the `identifier` field only. The per-contact ceiling holds
 *   when an attacker rotates IPs.
 * - `otp-send` (3 per 10 minutes and 10 per day per contact, 20 per hour and 50 per day per IP):
 *   sending a verification code, which costs money and can be abused to harass a third party.
 *   Keyed by the `contact` field only.
 * - `register` (the `otp-send` limits, for the email AND for the phone when present, plus the
 *   per-IP limits): registration sends a code to every contact given.
 * - `otp-verify` (5 per 15 minutes per contact whatever the IP, 30 per 15 minutes per IP):
 *   checking a code, so that a 6-digit code cannot be brute-forced. Keyed by `contact` only.
 * - `password-reset` (3 per hour per contact, 10 per hour per IP): requesting a reset code,
 *   keyed by `contact` only.
 * - `password-update` (5 per minute per account and IP, 20 per 15 minutes per account, 30 per
 *   minute per IP): changing the password of a signed-in account, which checks the current one.
 *   Keyed by the authenticated account, never shared with `login`.
 *
 * Contacts are normalized like the stored value, then replaced by a keyed hash (under
 * {@see DerivedKey::RATE_LIMITER}) so that no email or phone number is ever written to the cache.
 * Without a contact, only the per-IP limits apply, so contact-less requests cannot exhaust a
 * shared per-contact bucket. The limiter name is part of every key: counters are never shared
 * between limiters.
 */
final class AuthRateLimits
{
    /**
     * @return list<Limit>
     */
    public function login(?string $identifier, string $ip): array
    {
        $contact = $this->contactKey($identifier);

        return $this->withoutNulls([
            $contact !== null ? Limit::perMinute(5)->by("login:contact-ip:$contact|$ip") : null,
            $contact !== null ? Limit::perMinutes(15, 20)->by("login:contact:$contact") : null,
            Limit::perMinute(30)->by("login:ip:$ip"),
        ]);
    }

    /**
     * @return list<Limit>
     */
    public function otpSend(?string $contact, string $ip): array
    {
        return [
            ...$this->perContactSendLimits('otp-send', $this->contactKey($contact)),
            ...$this->perIpSendLimits('otp-send', $ip),
        ];
    }

    /**
     * @return list<Limit>
     */
    public function register(?string $email, ?string $phone, string $ip): array
    {
        return [
            ...$this->perContactSendLimits('register:email', $this->contactKey($email)),
            ...$this->perContactSendLimits('register:phone', $this->contactKey($phone)),
            ...$this->perIpSendLimits('register', $ip),
        ];
    }

    /**
     * @return list<Limit>
     */
    public function otpVerify(?string $contact, string $ip): array
    {
        $key = $this->contactKey($contact);

        return $this->withoutNulls([
            $key !== null ? Limit::perMinutes(15, 5)->by("otp-verify:contact:$key") : null,
            Limit::perMinutes(15, 30)->by("otp-verify:ip:$ip"),
        ]);
    }

    /**
     * @return list<Limit>
     */
    public function passwordReset(?string $contact, string $ip): array
    {
        $key = $this->contactKey($contact);

        return $this->withoutNulls([
            $key !== null ? Limit::perHour(3)->by("password-reset:contact:$key") : null,
            Limit::perHour(10)->by("password-reset:ip:$ip"),
        ]);
    }

    /**
     * @param  int|string|null  $userId  Identifier of the authenticated account; null for a guest.
     * @return list<Limit>
     */
    public function passwordUpdate(int|string|null $userId, string $ip): array
    {
        return $this->withoutNulls([
            $userId !== null ? Limit::perMinute(5)->by("password-update:user-ip:$userId|$ip") : null,
            $userId !== null ? Limit::perMinutes(15, 20)->by("password-update:user:$userId") : null,
            Limit::perMinute(30)->by("password-update:ip:$ip"),
        ]);
    }

    /**
     * Keyed hash of the normalized contact, or null when the value is not a usable contact.
     */
    public function contactKey(?string $contact): ?string
    {
        if ($contact === null || trim($contact) === '') {
            return null;
        }

        $normalized = ContactNormalizer::normalize($contact, (string) config('app.default_phone_country_code', '222'));

        return $normalized === null ? null : DerivedKey::hmac(DerivedKey::RATE_LIMITER, $normalized);
    }

    /**
     * @return list<Limit>
     */
    private function perContactSendLimits(string $prefix, ?string $contactKey): array
    {
        if ($contactKey === null) {
            return [];
        }

        return [
            Limit::perMinutes(10, 3)->by("$prefix:contact:$contactKey"),
            Limit::perDay(10)->by("$prefix:contact-day:$contactKey"),
        ];
    }

    /**
     * @return list<Limit>
     */
    private function perIpSendLimits(string $prefix, string $ip): array
    {
        return [
            Limit::perHour(20)->by("$prefix:ip:$ip"),
            Limit::perDay(50)->by("$prefix:ip-day:$ip"),
        ];
    }

    /**
     * @param  array<int, Limit|null>  $limits
     * @return list<Limit>
     */
    private function withoutNulls(array $limits): array
    {
        return array_values(array_filter($limits));
    }
}
