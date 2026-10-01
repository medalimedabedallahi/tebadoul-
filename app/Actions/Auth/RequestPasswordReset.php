<?php

namespace App\Actions\Auth;

use App\Enums\ContactPurpose;

/**
 * Sends a password reset code to the email address of an account.
 *
 * Anti-enumeration: returns nothing and behaves the same whether or not the contact belongs to an
 * account (see {@see SendContactVerification}: the lookup happens in a worker). A code is only
 * issued for a VERIFIED contact of an active account (see {@see IssueContactCode}).
 *
 * Callers are expected to throttle (`throttle:password-reset`).
 */
final class RequestPasswordReset
{
    public function __construct(private readonly SendContactVerification $sendVerification) {}

    /**
     * Validation rules for the input, shared by the API and Livewire.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return SendContactVerification::rules();
    }

    /**
     * @param  string  $contact  Email address in any accepted spelling.
     */
    public function handle(string $contact): void
    {
        $this->sendVerification->handle($contact, ContactPurpose::PasswordReset);
    }
}
