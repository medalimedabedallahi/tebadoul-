<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Jobs\ProcessContactCodeRequest;
use App\Rules\AsciiEmail;
use App\Support\ContactNormalizer;

/**
 * Requests a one-time code by email (contact verification or password reset).
 *
 * Anti-enumeration: the action returns nothing and does the SAME work whether or not the contact
 * belongs to an account: it normalizes the contact and queues a {@see ProcessContactCodeRequest}.
 * Looking the account up, checking eligibility and issuing the code all happen in the worker
 * ({@see IssueContactCode}), so neither the response nor its timing reveals anything. The job is
 * dispatched after the surrounding transaction commits (for example the one of
 * {@see RegisterUser}).
 *
 * Callers are expected to throttle (`throttle:otp-send`, `throttle:password-reset`,
 * `throttle:register`).
 */
final class SendContactVerification
{
    /**
     * Validation rules for the input, shared by the API and Livewire.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return ['contact' => ['required', 'string', 'email:rfc', new AsciiEmail, 'max:255']];
    }

    /**
     * @param  string  $contact  Email address in any accepted spelling.
     */
    public function handle(string $contact, ContactPurpose $purpose = ContactPurpose::ContactVerification): void
    {
        $normalized = ContactNormalizer::email($contact);

        if ($normalized === null) {
            return;
        }

        ProcessContactCodeRequest::dispatch($normalized, ContactType::Email, $purpose);
    }
}
