<?php

namespace App\Actions\Auth;

use App\Enums\ContactType;
use App\Enums\UserStatus;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Livewire\Auth\Login;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use App\Models\User;
use App\Rules\ContactIdentifier;
use App\Support\ContactNormalizer;
use Illuminate\Support\Facades\Hash;

/**
 * Checks an email address or phone number against a password and returns the matching account.
 *
 * Shared building block of {@see LoginUser} (API, issues a Sanctum token) and the web sign-in
 * component {@see Login} (session). Neither caller may duplicate this check: a change to how
 * credentials are verified must only ever happen here.
 *
 * Only a VERIFIED contact identifies an account: an email or a phone number that was never
 * verified (whether it belongs to an account pending verification or is a secondary contact of an
 * active one) is treated exactly like an unknown identifier. Consequently an account pending
 * verification can never sign in, and it fails exactly like an unknown identifier or a wrong
 * password; otherwise registering a contact with a password of one's choice and then signing in
 * would reveal whether that contact was already taken. The status is checked again after the
 * lookup (defense in depth).
 *
 * Anti-enumeration: an unknown identifier, an unverified contact, a pending account and a wrong
 * password all throw the same {@see InvalidCredentialsException}, and a dummy hash check equalizes
 * the response time. A suspended account is only reported once its password has been verified.
 *
 * Callers are expected to throttle (`throttle:login` for the API; the same limits, applied by
 * {@see AppliesNamedRateLimiter}, for the web component).
 */
final class AuthenticateCredentials
{
    private static ?string $dummyHash = null;

    /**
     * Validation rules for the input, shared by every caller.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255', new ContactIdentifier],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @param  string  $identifier  Email address or phone number, in any of the accepted spellings.
     * @return User An account whose status is {@see UserStatus::Active}.
     *
     * @throws InvalidCredentialsException Unknown or unverified identifier, pending account or wrong password (indistinguishable).
     * @throws AccountSuspendedException The credentials are right but the account is suspended.
     */
    public function handle(string $identifier, string $password): User
    {
        $normalized = ContactNormalizer::normalize($identifier, (string) config('app.default_phone_country_code', '222'));

        $user = $normalized === null
            ? null
            : User::query()->withVerifiedContact(ContactType::detect($identifier), $normalized)->first();

        $passwordMatches = Hash::check($password, $user->password ?? self::dummyHash());

        if ($user === null || ! $passwordMatches) {
            throw new InvalidCredentialsException;
        }

        if ($user->status === UserStatus::Suspended) {
            throw new AccountSuspendedException;
        }

        if (! $user->status->canSignIn()) {
            throw new InvalidCredentialsException;
        }

        return $user;
    }

    /**
     * A valid hash of a random password, made once per process, checked when the account does not
     * exist so that the two paths cost the same.
     */
    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(bin2hex(random_bytes(16)));
    }
}
