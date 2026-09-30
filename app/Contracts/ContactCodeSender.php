<?php

namespace App\Contracts;

use App\Models\ContactVerification;

/**
 * Delivers a one-time code to the contact it was issued for.
 */
interface ContactCodeSender
{
    /**
     * Send `$code` to `$verification->contact` through the channel of the verification (email or
     * SMS). Throws when the delivery fails, so that the queued job can retry.
     */
    public function send(ContactVerification $verification, string $code): void;
}
