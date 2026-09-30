<?php

namespace App\Support\ContactCodes;

use App\Contracts\ContactCodeSender;
use App\Models\ContactVerification;
use App\Notifications\ContactCodeNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers a code by email through an on-demand mail notification (Mailpit in development).
 */
final class EmailContactCodeSender implements ContactCodeSender
{
    public function send(ContactVerification $verification, string $code): void
    {
        Notification::route('mail', $verification->contact)->notify(
            (new ContactCodeNotification($code, $verification->purpose))->locale(RecipientLocale::of($verification))
        );
    }
}
