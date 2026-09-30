<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Models\User;
use App\Rules\ContactIdentifier;
use App\Support\ContactNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Verifies an email address or a phone number with the code that was sent to it.
 *
 * On success, in one transaction:
 * - the matching `email_verified_at` / `phone_verified_at` is set, and an account pending
 *   verification becomes {@see UserStatus::Active} (a suspended account stays suspended);
 * - every OTHER contact of the account that is still unverified is detached (with its unused
 *   codes): only contacts proven by their owner stay attached, so a contact typed by someone else
 *   at registration never remains reserved by this account;
 * - every Sanctum token of the account is revoked.
 *
 * The code is single-use, expires after `auth.verification.ttl_minutes`, and is locked after
 * `auth.verification.max_attempts` wrong guesses; see {@see ConsumeContactCode}.
 *
 * Anti-enumeration: every failure raises the same exception, whether the contact is unknown, the
 * code is wrong, expired, used or locked.
 *
 * Callers are expected to throttle (`throttle:otp-verify`).
 */
final class VerifyContact
{
    public function __construct(private readonly ConsumeContactCode $consumeCode) {}

    /**
     * Validation rules for the input, shared by the API and Livewire.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'contact' => ['required', 'string', 'max:255', new ContactIdentifier],
            'code' => ['required', 'string', 'regex:/^\d{4,9}$/'],
        ];
    }

    /**
     * @param  string  $contact  Email address or phone number, in any of the accepted spellings.
     * @param  string  $code  The code as typed, digits only.
     * @return User The account whose contact is now verified.
     *
     * @throws InvalidVerificationCodeException The code cannot be used (all causes indistinguishable).
     */
    public function handle(string $contact, string $code): User
    {
        $normalized = ContactNormalizer::normalize($contact, (string) config('app.default_phone_country_code', '222'));
        $channel = ContactType::detect($contact);

        // The closure returns (never throws) so that the attempt counter is committed even when the code is wrong.
        $user = $normalized === null ? null : DB::transaction(function () use ($normalized, $channel, $code): ?User {
            $verification = $this->consumeCode->handle($normalized, ContactPurpose::ContactVerification, $code);

            if ($verification === null) {
                return null;
            }

            $user = User::query()->lockForUpdate()->find($verification->user_id);

            if ($user === null || $user->{$channel->value} !== $normalized) {
                return null;
            }

            $user->{$channel->value.'_verified_at'} ??= now();

            foreach (ContactType::cases() as $other) {
                if ($other !== $channel && ! $user->hasVerified($other)) {
                    $user->detachContact($other);
                }
            }

            if ($user->status === UserStatus::PendingVerification) {
                $user->status = UserStatus::Active;
            }

            $user->save();
            $user->tokens()->delete();

            return $user;
        });

        return $user ?? throw new InvalidVerificationCodeException;
    }
}
