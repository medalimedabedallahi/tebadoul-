<?php

namespace App\Actions\Notifications;

use App\Models\User;

/**
 * Marks every unread notification of the account as read, in one conditional update.
 */
final class MarkAllNotificationsRead
{
    /**
     * @return int the number of notifications marked
     */
    public function handle(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
