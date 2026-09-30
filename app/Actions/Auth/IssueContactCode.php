<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Enums\UserStatus;
use App\Jobs\ProcessContactCodeRequest;
use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use App\Models\User;
use App\Support\VerificationCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Issues a one-time code for a normalized contact and queues its delivery (email or SMS).
 *
 * Runs in a worker ({@see ProcessContactCodeRequest}), never in the HTTP request: looking the
 * contact up and issuing a code take time only when the contact belongs to an account, and that
 * difference must not show in the response time of the public endpoints.
 *
 * Nothing is issued (silently) when:
 * - the contact belongs to no account, or the account is suspended;
 * - contact verification: the contact is already verified;
 * - password reset: the contact is not a VERIFIED contact of an active account (an unverified
 *   contact can never be used to take over an account).
 *
 * Only one code is active per (contact, purpose): issuing a new one supersedes the previous one.
 * The code is stored as a keyed hash; the clear code only travels in the encrypted payload of
 * {@see SendContactCode}, dispatched after the transaction commits.
 */
final class IssueContactCode
{
    /**
     * @param  string  $contact  Normalized email or E.164 phone number.
     */
    public function handle(string $contact, ContactType $channel, ContactPurpose $purpose): void
    {
        $user = $this->eligibleUser($contact, $channel, $purpose);

        if ($user === null) {
            return;
        }

        try {
            DB::transaction(function () use ($user, $channel, $contact, $purpose): void {
                ContactVerification::query()
                    ->unconsumed()
                    ->where('contact', $contact)
                    ->where('purpose', $purpose)
                    ->update(['consumed_at' => now()]);

                $code = VerificationCode::generate((int) config('auth.verification.code_length', 6));

                $verification = ContactVerification::query()->create([
                    'user_id' => $user->getKey(),
                    'purpose' => $purpose,
                    'channel' => $channel,
                    'contact' => $contact,
                    'code_hash' => VerificationCode::hash($contact, $purpose, $code),
                    'max_attempts' => (int) config('auth.verification.max_attempts', 5),
                    'expires_at' => now()->addMinutes((int) config('auth.verification.ttl_minutes', 10)),
                ]);

                SendContactCode::dispatch($verification->getKey(), $code);
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent request issued the active code first: that one is being delivered.
        }
    }

    private function eligibleUser(string $contact, ContactType $channel, ContactPurpose $purpose): ?User
    {
        return match ($purpose) {
            ContactPurpose::PasswordReset => User::query()
                ->withVerifiedContact($channel, $contact)
                ->where('status', UserStatus::Active)
                ->first(),
            ContactPurpose::ContactVerification => User::query()
                ->where($channel->value, $contact)
                ->whereNull($channel->value.'_verified_at')
                ->where('status', '!=', UserStatus::Suspended)
                ->first(),
        };
    }
}
