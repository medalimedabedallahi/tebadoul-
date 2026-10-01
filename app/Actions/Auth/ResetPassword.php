<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Models\User;
use App\Rules\AsciiEmail;
use App\Support\ContactNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Sets a new password with the code received through {@see RequestPasswordReset}.
 *
 * The code is single-use, expires, and is locked after too many wrong guesses (see
 * {@see ConsumeContactCode}). It is only accepted for a VERIFIED contact of an active account. On
 * success, in one transaction: the password is replaced, the "remember me" token is rotated, every
 * EVERY Sanctum token of the account is revoked, so a stolen token does not survive the reset.
 * The person signs in again afterwards.
 *
 * Anti-enumeration: every failure raises the same exception.
 *
 * Callers are expected to throttle (`throttle:otp-verify`).
 */
final class ResetPassword
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
            'contact' => ['required', 'string', 'email:rfc', new AsciiEmail, 'max:255'],
            'code' => ['required', 'string', 'regex:/^\d{4,9}$/'],
            'password' => ['required', 'string', Password::defaults(), 'max:255'],
        ];
    }

    /**
     * @param  string  $contact  Email address the code was sent to.
     * @param  string  $code  The code as typed, digits only.
     * @param  string  $password  The new password, already validated against {@see self::rules()}.
     *
     * @throws InvalidVerificationCodeException The code cannot be used (all causes indistinguishable).
     */
    public function handle(string $contact, string $code, string $password): void
    {
        $normalized = ContactNormalizer::email($contact);
        $channel = ContactType::Email;

        // The closure returns (never throws) so that the attempt counter is committed even when the code is wrong.
        $reset = $normalized !== null && DB::transaction(function () use ($normalized, $channel, $code, $password): bool {
            $verification = $this->consumeCode->handle($normalized, ContactPurpose::PasswordReset, $code);

            if ($verification === null) {
                return false;
            }

            $user = User::query()->lockForUpdate()->find($verification->user_id);

            if ($user === null
                || $user->{$channel->value} !== $normalized
                || ! $user->hasVerified($channel)
                || $user->status !== UserStatus::Active) {
                return false;
            }

            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();

            return true;
        });

        if (! $reset) {
            throw new InvalidVerificationCodeException;
        }
    }
}
