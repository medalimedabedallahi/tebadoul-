<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Support\RateLimiting\AuthRateLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

/**
 * V1 (security audit, fixed): the named limiters used to read the contact from the FIRST field
 * present among `identifier`, `login`, `contact`, `email`, `phone`, so a throwaway `identifier`
 * (or `login`) field sent next to the real `contact` keyed the limit on the throwaway value and the
 * per-contact ceiling never engaged. Each limiter now reads ONE explicit field (see
 * {@see AuthRateLimits}): extra fields are ignored and the per-contact limit holds.
 */
class RateLimiterContactSpoofingTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_v1_a_spoofed_identifier_field_does_not_bypass_the_otp_send_per_contact_limit(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'victime@example.com']);

        // 3 requests per 10 minutes for the same contact, whatever else the request carries.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/auth/contacts/verification/send', [
                'contact' => 'victime@example.com',
                'identifier' => "attacker-$attempt@example.com",
            ])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/contacts/verification/send', [
            'contact' => 'victime@example.com',
            'identifier' => 'attacker-99@example.com',
        ])->assertTooManyRequests();
    }

    public function test_v1_a_spoofed_identifier_field_does_not_bypass_the_otp_verify_per_contact_limit(): void
    {
        User::factory()->unverified()->create(['email' => 'victime@example.com']);

        // 5 guesses per 15 minutes for the same contact, whatever the IP or the extra fields.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.2.$attempt"])
                ->postJson('/api/v1/auth/contacts/verification/verify', [
                    'contact' => 'victime@example.com',
                    'code' => '000000',
                    'identifier' => "attacker-$attempt@example.com",
                ])->assertUnprocessable()->assertJsonPath('code', 'invalid_verification_code');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.99'])
            ->postJson('/api/v1/auth/contacts/verification/verify', [
                'contact' => 'victime@example.com',
                'code' => '000000',
                'identifier' => 'attacker-99@example.com',
            ])->assertTooManyRequests();
    }

    public function test_v1_a_spoofed_login_field_does_not_bypass_the_password_reset_per_contact_limit(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'victime@example.com']);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/v1/auth/password/forgot', [
                'contact' => 'victime@example.com',
                'login' => "attacker-$attempt@example.com",
                'email' => "attacker-$attempt@example.com",
            ])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/password/forgot', [
            'contact' => 'victime@example.com',
            'login' => 'attacker-99@example.com',
        ])->assertTooManyRequests();
    }

    public function test_v1_a_spoofed_contact_field_does_not_bypass_the_login_per_contact_limit(): void
    {
        User::factory()->create(['email' => 'victime@example.com', 'password' => self::PASSWORD]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'identifier' => 'victime@example.com',
                'password' => 'wrong-password-123',
                'contact' => "attacker-$attempt@example.com",
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'identifier' => 'victime@example.com',
            'password' => self::PASSWORD,
            'contact' => 'attacker-99@example.com',
        ])->assertTooManyRequests();
    }
}
