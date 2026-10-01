<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\SendContactVerification;
use App\Enums\ContactPurpose;
use App\Enums\ContactType;
use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use App\Models\User;
use App\Support\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class SendContactVerificationTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_issues_a_six_digit_code_stored_only_as_a_keyed_hash_and_queues_its_delivery(): void
    {
        $this->captureContactCodes();
        $this->freezeSecond();
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);

        app(SendContactVerification::class)->handle('A@Example.com');

        $verification = $this->activeVerification('a@example.com');
        $code = $this->lastCodeFor($verification);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertTrue($verification->user->is($user));
        $this->assertSame(ContactPurpose::ContactVerification, $verification->purpose);
        $this->assertSame(ContactType::Email, $verification->channel);
        $this->assertSame(0, $verification->attempts);
        $this->assertSame(5, $verification->max_attempts);
        $this->assertTrue($verification->expires_at->equalTo(now()->addMinutes(10)));
        $this->assertNotSame($code, $verification->code_hash);
        $this->assertTrue(VerificationCode::matches($verification->code_hash, 'a@example.com', ContactPurpose::ContactVerification, $code));
        $this->assertDatabaseMissing('contact_verifications', ['code_hash' => $code]);
    }

    public function test_the_job_is_queued_on_the_notifications_queue_and_carries_no_contact(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        app(SendContactVerification::class)->handle('a@example.com');

        Queue::assertPushedOn('notifications', SendContactCode::class);
        $job = Queue::pushed(SendContactCode::class)->sole();
        $this->assertStringNotContainsString('a@example.com', serialize($job));
    }

    public function test_does_not_send_to_a_phone_only_account(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => null, 'phone' => '+22241111111', 'phone_verified_at' => null]);

        app(SendContactVerification::class)->handle('41 11 11 11');

        $this->assertSame(0, ContactVerification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_new_code_supersedes_the_previous_one(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        app(SendContactVerification::class)->handle('a@example.com');
        $first = $this->activeVerification('a@example.com');
        app(SendContactVerification::class)->handle('a@example.com');

        $this->assertSame(2, ContactVerification::query()->count());
        $this->assertNotNull($first->fresh()->consumed_at);
        $this->assertSame(1, ContactVerification::query()->unconsumed()->count());
        $this->assertNotSame($first->getKey(), $this->activeVerification('a@example.com')->getKey());
    }

    public function test_an_unknown_contact_gets_nothing_and_no_error(): void
    {
        $this->captureContactCodes();

        app(SendContactVerification::class)->handle('ghost@example.com');
        app(SendContactVerification::class)->handle('+22249999999');

        $this->assertSame(0, ContactVerification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_an_already_verified_contact_gets_no_verification_code(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'a@example.com']);

        app(SendContactVerification::class)->handle('a@example.com');

        $this->assertSame(0, ContactVerification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_a_suspended_account_gets_no_code(): void
    {
        $this->captureContactCodes();
        User::factory()->suspended()->unverified()->create(['email' => 's@example.com']);

        app(SendContactVerification::class)->handle('s@example.com');
        app(SendContactVerification::class)->handle('s@example.com', ContactPurpose::PasswordReset);

        Queue::assertNothingPushed();
    }

    public function test_the_code_length_and_lifetime_follow_the_configuration(): void
    {
        $this->captureContactCodes();
        $this->freezeSecond();
        config(['auth.verification.code_length' => 8, 'auth.verification.ttl_minutes' => 3, 'auth.verification.max_attempts' => 2]);
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        app(SendContactVerification::class)->handle('a@example.com');

        $verification = $this->activeVerification('a@example.com');
        $this->assertMatchesRegularExpression('/^\d{8}$/', $this->lastCodeFor($verification));
        $this->assertSame(2, $verification->max_attempts);
        $this->assertTrue($verification->expires_at->equalTo(now()->addMinutes(3)));
    }

    public function test_generated_codes_keep_their_leading_zeros(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->assertMatchesRegularExpression('/^\d{6}$/', VerificationCode::generate(6));
        }
    }

    public function test_rules_require_an_email(): void
    {
        $this->assertTrue(Validator::make(['contact' => 'a@example.com'], SendContactVerification::rules())->passes());
        $this->assertTrue(Validator::make(['contact' => '41 11 11 11'], SendContactVerification::rules())->fails());
        $this->assertTrue(Validator::make(['contact' => 'nonsense'], SendContactVerification::rules())->fails());
        $this->assertTrue(Validator::make([], SendContactVerification::rules())->fails());
    }
}
