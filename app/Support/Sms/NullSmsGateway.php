<?php

namespace App\Support\Sms;

use App\Contracts\SmsGateway;

/**
 * Discards every message. Selected with SMS_DRIVER=null.
 */
final class NullSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        //
    }
}
