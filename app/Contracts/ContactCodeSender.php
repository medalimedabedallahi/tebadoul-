<?php

namespace App\Contracts;

use App\Models\ContactVerification;

/**
 * Delivers a one-time code to the contact it was issued for.
 */
interface ContactCodeSender
{
    /**
     * Send `$code` to the verified email destination. Throws when delivery fails, so that the
     * queued job can retry.
     */
    public function send(ContactVerification $verification, string $code): void;
}
