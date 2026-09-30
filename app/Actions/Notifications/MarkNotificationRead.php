<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/**
 * Marks one of the account's notifications as read. Idempotent: the first read date is kept. A
 * notification of another account is reported exactly like an unknown one (404).
 */
final class MarkNotificationRead
{
    /**
     * @throws ModelNotFoundException
     */
    public function handle(User $user, string $notificationId): DatabaseNotification
    {
        if (! Str::isUuid($notificationId)) {
            throw (new ModelNotFoundException)->setModel(DatabaseNotification::class, [$notificationId]);
        }

        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey(Str::lower($notificationId))->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $notification;
    }
}
