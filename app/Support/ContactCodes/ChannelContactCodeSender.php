<?php

namespace App\Support\ContactCodes;

use App\Contracts\ContactCodeSender;
use App\Enums\ContactType;
use App\Models\ContactVerification;

/**
 * The sender bound to {@see ContactCodeSender}: picks email or SMS from the channel of the code.
 */
final class ChannelContactCodeSender implements ContactCodeSender
{
    public function __construct(
        private readonly EmailContactCodeSender $email,
        private readonly SmsContactCodeSender $sms,
    ) {}

    public function send(ContactVerification $verification, string $code): void
    {
        match ($verification->channel) {
            ContactType::Email => $this->email->send($verification, $code),
            ContactType::Phone => $this->sms->send($verification, $code),
        };
    }
}
