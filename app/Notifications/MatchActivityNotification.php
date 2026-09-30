<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Jobs\SendNotificationEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email copy of an in-app notification. It names neither the other participant nor any detail of
 * the match: the reader follows the link and signs in. Not queued itself, because
 * {@see SendNotificationEmail} already runs in a worker.
 */
class MatchActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly NotificationType $type,
        public readonly string $matchPublicId,
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
        return (new MailMessage)
            ->subject(__('notifications.mail.subject', ['event' => __('notifications.types.'.$this->type->value)]))
            ->greeting(__('notifications.mail.greeting'))
            ->line(__('notifications.types.'.$this->type->value))
            ->action(__('notifications.mail.action'), route('matches.show', $this->matchPublicId))
            ->line(__('notifications.mail.preferences'))
            ->salutation(__('notifications.mail.salutation'));
    }
}
