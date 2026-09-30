<?php

namespace Tests\Support;

use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use Illuminate\Support\Facades\Queue;

/**
 * Helpers for tests that need the clear one-time code, which only exists in the queued job.
 */
trait InteractsWithContactCodes
{
    protected const PASSWORD = 'Correct-horse-2026-battery';

    /**
     * Capture delivery jobs instead of running them.
     */
    protected function captureContactCodes(): void
    {
        Queue::fake([SendContactCode::class]);
    }

    /**
     * The clear code of the last delivery job pushed for the verification.
     */
    protected function lastCodeFor(ContactVerification $verification): string
    {
        $job = Queue::pushed(SendContactCode::class)
            ->filter(fn (SendContactCode $job): bool => $job->verificationId === $verification->getKey())
            ->last();

        $this->assertNotNull($job, 'No delivery job was pushed for this verification.');

        return $job->code;
    }

    /**
     * The clear code of the last delivery job pushed, whatever the contact.
     */
    protected function lastCode(): string
    {
        $job = Queue::pushed(SendContactCode::class)->last();

        $this->assertNotNull($job, 'No delivery job was pushed.');

        return $job->code;
    }

    /**
     * The verification currently active (not consumed) for a contact.
     */
    protected function activeVerification(string $contact): ?ContactVerification
    {
        return ContactVerification::query()->unconsumed()->where('contact', $contact)->first();
    }

    /**
     * Drop the user cached by the guard so that the next request authenticates again (needed
     * between two `withToken()` calls in the same test: Sanctum otherwise keeps the first user).
     */
    protected function forgetResolvedUser(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
