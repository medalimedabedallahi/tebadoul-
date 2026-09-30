<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\V1\RateLimitingTest;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

/**
 * Each real endpoint is protected by the named limiter it is documented to use (see
 * AppServiceProvider::configureRateLimiting()); {@see RateLimitingTest}
 * already covers the limiter rules themselves in isolation.
 */
class AuthThrottlingTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_attempts_for_the_same_contact(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['identifier' => 'a@example.com', 'password' => 'wrong-password-123'])
                ->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', ['identifier' => 'a@example.com', 'password' => 'wrong-password-123'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }

    public function test_verification_send_is_throttled_after_three_requests_for_the_same_contact(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/contacts/verification/send', ['contact' => 'a@example.com'])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/contacts/verification/send', ['contact' => 'a@example.com'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');
    }

    public function test_verification_verify_is_throttled_after_five_attempts_for_the_same_contact(): void
    {
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/contacts/verification/verify', ['contact' => 'a@example.com', 'code' => '000000'])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/contacts/verification/verify', ['contact' => 'a@example.com', 'code' => '000000'])
            ->assertTooManyRequests();
    }

    public function test_password_forgot_is_throttled_after_three_requests_for_the_same_contact(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'a@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/password/forgot', ['contact' => 'a@example.com'])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/password/forgot', ['contact' => 'a@example.com'])
            ->assertTooManyRequests();
    }

    public function test_register_is_throttled_by_the_otp_send_limiter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/register', [
                'accept_terms' => true,
                'name' => 'A', 'email' => 'a@example.com', 'password' => self::PASSWORD,
            ])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/register', ['accept_terms' => true, 'name' => 'A', 'email' => 'a@example.com', 'password' => self::PASSWORD])
            ->assertTooManyRequests();
    }
}
