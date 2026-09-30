<?php

namespace App\Jobs;

use App\Contracts\ContactCodeSender;
use App\Exceptions\RedactedJobException;
use App\Models\ContactVerification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a one-time code to its contact (email or SMS).
 *
 * The clear code has to travel to the worker, so the payload is encrypted ({@see ShouldBeEncrypted})
 * and the job is dispatched only once the transaction that stored the hash has committed.
 *
 * The job is idempotent per verification: the send is claimed atomically by setting `last_sent_at`
 * on a code that is still usable and has not been sent yet, so a duplicate delivery of the job, a
 * retry after a worker crash or a double dispatch sends one message at most. When the delivery
 * itself fails, the claim is released so that the retry can send. A code that was used, superseded
 * or has expired in the meantime is never sent.
 */
class SendContactCode implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 30;

    public function __construct(
        public readonly int $verificationId,
        public readonly string $code,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(ContactCodeSender $sender): void
    {
        $claimed = ContactVerification::query()
            ->whereKey($this->verificationId)
            ->whereNull('consumed_at')
            ->whereNull('last_sent_at')
            ->where('expires_at', '>', now())
            ->update(['last_sent_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $verification = ContactVerification::query()->with('user')->findOrFail($this->verificationId);

        try {
            $sender->send($verification, $this->code);
        } catch (Throwable $e) {
            ContactVerification::query()->whereKey($this->verificationId)->update(['last_sent_at' => null]);

            // A mail or SMS transport error usually names the recipient: only its class and code
            // reach the exception handler and `failed_jobs`.
            throw RedactedJobException::from($e, 'Contact code delivery');
        }
    }

    /**
     * Called once every attempt has failed. Neither the code, the contact nor the exception
     * message (which a mail or SMS transport may fill with the recipient) is written.
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Contact code delivery failed', [
            'verification_id' => $this->verificationId,
            'exception' => $exception instanceof RedactedJobException
                ? $exception->originalClass
                : ($exception !== null ? $exception::class : null),
        ]);
    }
}
