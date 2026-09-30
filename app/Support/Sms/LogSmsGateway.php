<?php

namespace App\Support\Sms;

use App\Contracts\SmsGateway;
use App\Support\ContactMasker;
use Illuminate\Support\Facades\Log;

/**
 * Development and test driver: records that an SMS would have been sent, and nothing else.
 *
 * The message contains the one-time code and the recipient is personal data, so neither is ever
 * written: the log line carries the masked number and the length of the message only.
 */
final class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): void
    {
        Log::info('SMS not delivered (log driver)', [
            'to' => ContactMasker::phone($to),
            'length' => mb_strlen($message),
        ]);
    }
}
