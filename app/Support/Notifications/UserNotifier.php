<?php

namespace App\Support\Notifications;

use App\Enums\NotificationType;
use App\Enums\UserStatus;
use App\Jobs\SendNotificationEmail;
use App\Models\MobilityMatch;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Tells a participant about an event on one of their matches (PRD FR 013).
 *
 * Call it inside the transaction that changes the match: the in-app notification is written with
 * the change (both or neither), and the optional email copy is queued only once it commits
 * ({@see SendNotificationEmail}). The match row is locked by every caller, so the checks below
 * are not raced.
 *
 * No duplicates (PRD 16): while the recipient has not read a notification of the same type about
 * the same match, a new event adds nothing (a burst of messages is one notification). The data
 * holds the match public id only, never a name, contact detail or message body.
 */
final class UserNotifier
{
    public function notify(User $recipient, NotificationType $type, MobilityMatch $match): void
    {
        if ($recipient->status !== UserStatus::Active) {
            return;
        }

        $alreadyUnread = $recipient->unreadNotifications()
            ->where('type', $type->value)
            ->where('data->match_public_id', $match->public_id)
            ->exists();

        if ($alreadyUnread) {
            return;
        }

        $notification = $recipient->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type->value,
            'data' => ['match_public_id' => $match->public_id],
        ]);

        if ($type->sendsEmail() && self::acceptsEmail($recipient)) {
            SendNotificationEmail::dispatch((string) $notification->getKey());
        }
    }

    /**
     * An email copy goes only to an active account that kept the preference on and has a
     * verified address.
     */
    public static function acceptsEmail(User $user): bool
    {
        return $user->status === UserStatus::Active
            && $user->email_notifications
            && $user->email !== null
            && $user->email_verified_at !== null;
    }
}
