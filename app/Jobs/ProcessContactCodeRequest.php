<?php

namespace App\Jobs;

use App\Actions\Auth\IssueContactCode;
use App\Actions\Auth\SendContactVerification;
use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Exceptions\RedactedJobException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles a request for a one-time code ({@see SendContactVerification}) in a worker: looks the
 * contact up and, when it is eligible, issues the code ({@see IssueContactCode}).
 *
 * Moving this work out of the HTTP request makes the response time of the public "send a code"
 * endpoints independent of whether the contact belongs to an account. The contact is personal
 * data, so the payload is encrypted, and an exception is replaced by a {@see RedactedJobException}
 * (a query error would otherwise carry the contact into the logs and `failed_jobs`).
 */
class ProcessContactCodeRequest implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30];

    public int $timeout = 30;

    /**
     * @param  string  $contact  Normalized email or E.164 phone number.
     */
    public function __construct(
        public readonly string $contact,
        public readonly ContactType $channel,
        public readonly ContactPurpose $purpose,
    ) {
        $this->onQueue('notifications');
        $this->afterCommit();
    }

    public function handle(IssueContactCode $issueCode): void
    {
        try {
            $issueCode->handle($this->contact, $this->channel, $this->purpose);
        } catch (Throwable $e) {
            throw RedactedJobException::from($e, 'Contact code request');
        }
    }

    /**
     * Called once every attempt has failed. The contact is never written.
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Contact code request failed', [
            'purpose' => $this->purpose->value,
            'channel' => $this->channel->value,
            'exception' => $exception instanceof RedactedJobException ? $exception->originalClass : ($exception !== null ? $exception::class : null),
        ]);
    }
}
