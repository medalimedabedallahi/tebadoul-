<?php

namespace App\Notifications;

use App\Enums\ContactPurpose;
use App\Jobs\SendContactCode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email carrying a one-time code. Sent on demand to a contact address; it is not queued itself
 * because {@see SendContactCode} already runs in a worker (queuing it again would copy
 * the clear code into a second payload).
 */
class ContactCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly ContactPurpose $purpose,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isReset = $this->purpose === ContactPurpose::PasswordReset;

        return (new MailMessage)
            ->subject($isReset ? __('Your Tebadoul password reset code') : __('Your Tebadoul verification code'))
            ->line($isReset ? __('Your password reset code is:') : __('Your verification code is:'))
            ->line('**'.$this->code.'**')
            ->line(__('This code is valid for :minutes minutes. Do not share it with anyone.', [
                'minutes' => (int) config('auth.verification.ttl_minutes'),
            ]))
            ->line(__('If you did not request this code, you can ignore this message.'));
    }
}
