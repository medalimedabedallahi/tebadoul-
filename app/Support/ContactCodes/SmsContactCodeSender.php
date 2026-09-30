<?php

namespace App\Support\ContactCodes;

use App\Contracts\ContactCodeSender;
use App\Contracts\SmsGateway;
use App\Enums\ContactPurpose;
use App\Models\ContactVerification;

/**
 * Delivers a code by SMS through the configured {@see SmsGateway} driver.
 */
final class SmsContactCodeSender implements ContactCodeSender
{
    public function __construct(private readonly SmsGateway $gateway) {}

    public function send(ContactVerification $verification, string $code): void
    {
        $locale = RecipientLocale::of($verification);
        $replace = ['code' => $code, 'minutes' => (int) config('auth.verification.ttl_minutes')];

        $message = match ($verification->purpose) {
            ContactPurpose::ContactVerification => __('Badal: your verification code is :code. It is valid for :minutes minutes. Do not share it.', $replace, $locale),
            ContactPurpose::PasswordReset => __('Badal: your password reset code is :code. It is valid for :minutes minutes. Do not share it.', $replace, $locale),
        };

        $this->gateway->send($verification->contact, $message);
    }
}
