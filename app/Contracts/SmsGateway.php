<?php

namespace App\Contracts;

/**
 * Transport for text messages. The driver is chosen by `services.sms.driver` (SMS_DRIVER).
 */
interface SmsGateway
{
    /**
     * Send `$message` to the E.164 number `$to`. Implementations must not write the message or the
     * full number to logs: the message contains a one-time code.
     */
    public function send(string $to, string $message): void;
}
