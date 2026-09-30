<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Jobs\ProcessContactCodeRequest;
use App\Rules\ContactIdentifier;
use App\Support\ContactNormalizer;

/**
 * Requests a one-time code for a contact (contact verification or password reset).
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
        return ['contact' => ['required', 'string', 'max:255', new ContactIdentifier]];
    }

    /**
     * @param  string  $contact  Email address or phone number, in any of the accepted spellings.
     */
    public function handle(string $contact, ContactPurpose $purpose = ContactPurpose::ContactVerification): void
    {
        $normalized = ContactNormalizer::normalize($contact, (string) config('app.default_phone_country_code', '222'));

        if ($normalized === null) {
            return;
        }

        ProcessContactCodeRequest::dispatch($normalized, ContactType::detect($contact), $purpose);
    }
}
