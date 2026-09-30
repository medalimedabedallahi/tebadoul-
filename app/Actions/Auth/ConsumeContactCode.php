<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;
use App\Models\ContactVerification;
use App\Support\VerificationCode;
use Illuminate\Support\Facades\DB;

/**
 * Checks a one-time code and, when it is right, consumes it. Building block of
 * {@see VerifyContact} and {@see ResetPassword}; it is not a use case of its own.
 *
 * The active code of the (contact, purpose) pair is locked while it is examined, so two
 * simultaneous submissions cannot both succeed. A submission is counted as an attempt before the
 * comparison, the comparison runs in constant time, and a code that reached `max_attempts` stays
 * locked until a new one is issued. Unknown contact, expired code, locked code and wrong code are
 * all reported the same way (null), so the caller cannot tell them apart.
 *
 * The attempt counter must survive a wrong guess: the caller must therefore RETURN (never throw)
 * from its own transaction when this action returns null, and throw only after the commit.
 */
final class ConsumeContactCode
{
    /**
     * @param  string  $contact  Normalized email or E.164 phone number.
     * @param  string  $code  The code as typed by the person.
     * @return ContactVerification|null The verification, now consumed, when the code is right; null otherwise.
     */
    public function handle(string $contact, ContactPurpose $purpose, string $code): ?ContactVerification
    {
        return DB::transaction(function () use ($contact, $purpose, $code): ?ContactVerification {
            $verification = ContactVerification::query()
                ->unconsumed()
                ->where('contact', $contact)
                ->where('purpose', $purpose)
                ->lockForUpdate()
                ->first();

            if ($verification === null) {
                // Same amount of work as a real check, so that timing does not reveal the absence of a code.
                VerificationCode::matches(str_repeat('0', 64), $contact, $purpose, $code);

                return null;
            }

            if ($verification->isExpired() || $verification->isLocked()) {
                return null;
            }

            $verification->increment('attempts');

            if (! VerificationCode::matches($verification->code_hash, $contact, $purpose, $code)) {
                return null;
            }

            $verification->forceFill(['consumed_at' => now()])->save();

            return $verification;
        });
    }
}
