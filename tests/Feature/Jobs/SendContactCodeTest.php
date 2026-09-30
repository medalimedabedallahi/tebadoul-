<?php

namespace Tests\Feature\Jobs;

use App\Contracts\ContactCodeSender;
use App\Contracts\SmsGateway;
use App\Enums\ContactPurpose;
use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use App\Models\User;
use App\Notifications\ContactCodeNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\RecordingSmsGateway;
use Tests\TestCase;

class SendContactCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_a_queued_encrypted_job_with_retries_that_waits_for_the_commit(): void
    {
        $job = new SendContactCode(1, '123456');

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
        $this->assertTrue($job->afterCommit);
        $this->assertSame('notifications', $job->queue);
    }

    public function test_sends_the_email_once_when_the_job_runs_twice(): void
    {
        Notification::fake();
        $verification = ContactVerification::factory()->create(['contact' => 'a@example.com']);

        $job = new SendContactCode($verification->getKey(), '123456');
        $job->handle(app(ContactCodeSender::class));
        $job->handle(app(ContactCodeSender::class));

        Notification::assertSentOnDemandTimes(ContactCodeNotification::class, 1);
        Notification::assertSentOnDemand(
            ContactCodeNotification::class,
            fn (ContactCodeNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'a@example.com',
        );
        $this->assertNotNull($verification->fresh()->last_sent_at);
    }

    public function test_sends_the_sms_once_when_the_job_runs_twice(): void
    {
        $gateway = new RecordingSmsGateway;
        $this->app->instance(SmsGateway::class, $gateway);
        $verification = ContactVerification::factory()->forPhone('+22241111111')->create();

        $job = new SendContactCode($verification->getKey(), '654321');
        $job->handle(app(ContactCodeSender::class));
        $job->handle(app(ContactCodeSender::class));

        $this->assertCount(1, $gateway->sent);
        $this->assertSame('+22241111111', $gateway->sent[0]['to']);
        $this->assertSame('654321', $gateway->lastCode());
    }

    public function test_two_jobs_for_the_same_verification_send_one_message(): void
    {
        Notification::fake();
        $verification = ContactVerification::factory()->create();

        (new SendContactCode($verification->getKey(), '123456'))->handle(app(ContactCodeSender::class));
        (new SendContactCode($verification->getKey(), '123456'))->handle(app(ContactCodeSender::class));

        Notification::assertSentOnDemandTimes(ContactCodeNotification::class, 1);
    }

    public function test_does_not_send_a_code_that_was_superseded_or_used(): void
    {
        Notification::fake();
        $consumed = ContactVerification::factory()->consumed()->create();

        (new SendContactCode($consumed->getKey(), '123456'))->handle(app(ContactCodeSender::class));

        Notification::assertNothingSent();
    }

    public function test_does_not_send_an_expired_code(): void
    {
        Notification::fake();
        $expired = ContactVerification::factory()->expired()->create();

        (new SendContactCode($expired->getKey(), '123456'))->handle(app(ContactCodeSender::class));

        Notification::assertNothingSent();
    }

    public function test_does_not_send_when_the_verification_no_longer_exists(): void
    {
        Notification::fake();

        (new SendContactCode(999_999, '123456'))->handle(app(ContactCodeSender::class));

        Notification::assertNothingSent();
    }

    public function test_a_failed_delivery_releases_the_claim_so_that_the_retry_sends(): void
    {
        $verification = ContactVerification::factory()->forPhone('+22241111111')->create();
        $failing = new class implements SmsGateway
        {
            public function send(string $to, string $message): void
            {
                throw new RuntimeException('provider down');
            }
        };
        $this->app->instance(SmsGateway::class, $failing);
        $job = new SendContactCode($verification->getKey(), '123456');

        try {
            $job->handle(app(ContactCodeSender::class));
            $this->fail('The failure must reach the queue so that it retries.');
        } catch (RuntimeException) {
            $this->assertNull($verification->fresh()->last_sent_at);
        }

        $gateway = new RecordingSmsGateway;
        $this->app->instance(SmsGateway::class, $gateway);
        $job->handle(app(ContactCodeSender::class));

        $this->assertCount(1, $gateway->sent);
    }

    public function test_dispatching_pushes_the_job_with_its_verification_and_code(): void
    {
        Queue::fake();

        SendContactCode::dispatch(1, '123456');

        Queue::assertPushed(SendContactCode::class, fn (SendContactCode $job): bool => $job->verificationId === 1 && $job->code === '123456');
    }

    public function test_the_email_is_written_in_the_language_of_the_recipient(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['email' => 'a@example.com', 'locale' => 'ar']);
        $verification = ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);

        (new SendContactCode($verification->getKey(), '123456'))->handle(app(ContactCodeSender::class));

        Notification::assertSentOnDemand(
            ContactCodeNotification::class,
            fn (ContactCodeNotification $notification, array $channels, object $notifiable): bool => $notification->locale === 'ar',
        );
    }

    public function test_the_email_carries_the_code_and_the_lifetime_and_no_link(): void
    {
        $mail = (new ContactCodeNotification('987654', ContactPurpose::ContactVerification))
            ->toMail(new \stdClass);

        $rendered = (string) $mail->render();

        $this->assertStringContainsString('987654', $rendered);
        $this->assertStringContainsString('10', $rendered);
        $this->assertNull($mail->actionUrl);
    }
}
