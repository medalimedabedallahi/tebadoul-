<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Exceptions\RedactedJobException;
use App\Models\User;
use App\Notifications\MatchActivityNotification;
use App\Support\ContactCodes\RecipientLocale;
use App\Support\Notifications\UserNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the email copy of one in-app notification, on the `notifications` queue, once the
 * transaction that recorded it has committed.
 *
 * Idempotent: the send is claimed atomically by setting `mailed_at`, so a duplicate delivery of
 * the job or a retry after a worker crash sends one email at most. A failed delivery releases the
 * claim for the retry. Nothing is sent when the notification was read in the meantime, or when
 * the account no longer accepts emails (preference turned off, suspended, address removed).
 */
class SendNotificationEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 30;

    public function __construct(public readonly string $notificationId)
    {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(): void
    {
        $claimed = DatabaseNotification::query()
            ->whereKey($this->notificationId)
            ->whereNull('mailed_at')
            ->whereNull('read_at')
            ->update(['mailed_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $notification = DatabaseNotification::query()->with('notifiable')->findOrFail($this->notificationId);
        $recipient = $notification->notifiable;
        $type = NotificationType::tryFrom($notification->type);
        $matchPublicId = $notification->data['match_public_id'] ?? null;

        if (! $recipient instanceof User || $type === null || ! is_string($matchPublicId) || ! UserNotifier::acceptsEmail($recipient)) {
            return;
        }

        try {
            $recipient->notifyNow((new MatchActivityNotification($type, $matchPublicId))->locale(RecipientLocale::forUser($recipient)));
        } catch (Throwable $e) {
            DatabaseNotification::query()->whereKey($this->notificationId)->update(['mailed_at' => null]);

            // A mail transport error usually names the recipient: only its class and code reach
            // the exception handler and `failed_jobs`.
            throw RedactedJobException::from($e, 'Notification email delivery');
        }
    }

    /**
     * Called once every attempt has failed. Neither the address nor the exception message is
     * written.
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Notification email delivery failed', [
            'notification_id' => $this->notificationId,
            'exception' => $exception instanceof RedactedJobException
                ? $exception->originalClass
                : ($exception !== null ? $exception::class : null),
        ]);
    }
}
