<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['login', 'otp-send', 'otp-verify', 'password-reset', 'register'] as $limiter) {
            Route::middleware(['api', "throttle:$limiter"])->post("/api/v1/testing/$limiter", fn () => ['ok' => true]);
        }
    }

    public function test_login_blocks_the_sixth_attempt_for_the_same_contact_and_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/testing/login', ['identifier' => 'user@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/testing/login', ['identifier' => 'user@example.com'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }

    public function test_login_counts_differently_written_versions_of_a_contact_together(): void
    {
        $spellings = ['user@example.com', '  USER@example.com ', 'User@Example.com', 'user@EXAMPLE.com', 'uSeR@example.com'];

        foreach ($spellings as $identifier) {
            $this->postJson('/api/v1/testing/login', ['identifier' => $identifier])->assertOk();
        }

        $this->postJson('/api/v1/testing/login', ['identifier' => ' USER@example.com'])->assertTooManyRequests();
    }

    public function test_login_counts_a_national_and_an_international_phone_number_together(): void
    {
        $spellings = ['41 11 11 11', '+222 41111111', '00222 41 11 11 11', '+222-4111-1111', '41111111'];

        foreach ($spellings as $identifier) {
            $this->postJson('/api/v1/testing/login', ['identifier' => $identifier])->assertOk();
        }

        $this->postJson('/api/v1/testing/login', ['identifier' => '+22241111111'])->assertTooManyRequests();
    }

    public function test_login_does_not_block_another_contact_from_the_same_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/testing/login', ['identifier' => 'first@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/testing/login', ['identifier' => 'second@example.com'])->assertOk();
    }

    public function test_login_caps_a_contact_even_when_the_attacker_rotates_ip_addresses(): void
    {
        for ($ip = 1; $ip <= 4; $ip++) {
            for ($attempt = 1; $attempt <= 5; $attempt++) {
                $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$ip"])
                    ->postJson('/api/v1/testing/login', ['identifier' => 'victim@example.com'])
                    ->assertOk();
            }
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->postJson('/api/v1/testing/login', ['identifier' => 'victim@example.com'])
            ->assertTooManyRequests();
    }

    public function test_login_limits_an_ip_that_sends_requests_without_any_contact(): void
    {
        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->postJson('/api/v1/testing/login')->assertOk();
        }

        $this->postJson('/api/v1/testing/login')->assertTooManyRequests();
    }

    public function test_otp_send_allows_three_codes_per_ten_minutes_then_recovers(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/testing/otp-send', ['contact' => '+22241111111'])->assertOk();
        }

        $this->postJson('/api/v1/testing/otp-send', ['contact' => '+22241111111'])->assertTooManyRequests();

        $this->travel(11)->minutes();

        $this->postJson('/api/v1/testing/otp-send', ['contact' => '+22241111111'])->assertOk();
    }

    public function test_otp_send_applies_a_daily_ceiling_per_contact(): void
    {
        $sent = 0;

        for ($window = 1; $window <= 4; $window++) {
            for ($attempt = 1; $attempt <= 3 && $sent < 10; $attempt++) {
                $this->postJson('/api/v1/testing/otp-send', ['contact' => 'daily@example.com'])->assertOk();
                $sent++;
            }

            $this->travel(11)->minutes();
        }

        $this->postJson('/api/v1/testing/otp-send', ['contact' => 'daily@example.com'])->assertTooManyRequests();
    }

    public function test_otp_send_limits_an_ip_that_targets_many_contacts(): void
    {
        for ($contact = 1; $contact <= 20; $contact++) {
            $this->postJson('/api/v1/testing/otp-send', ['contact' => "+2224000{$contact}00"])->assertOk();
        }

        $this->postJson('/api/v1/testing/otp-send', ['contact' => '+22249999999'])->assertTooManyRequests();
    }

    public function test_otp_verify_allows_five_guesses_per_contact_whatever_the_ip(): void
    {
        for ($ip = 1; $ip <= 5; $ip++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.1.$ip"])
                ->postJson('/api/v1/testing/otp-verify', ['contact' => '+22241111111', 'code' => '000000'])
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.6'])
            ->postJson('/api/v1/testing/otp-verify', ['contact' => '+22241111111', 'code' => '000000'])
            ->assertTooManyRequests();
    }

    public function test_password_reset_allows_three_requests_per_hour_per_contact(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/testing/password-reset', ['contact' => 'user@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/testing/password-reset', ['contact' => 'user@example.com'])->assertTooManyRequests();
        $this->postJson('/api/v1/testing/password-reset', ['contact' => 'other@example.com'])->assertOk();
    }

    public function test_the_limiters_do_not_share_their_counters(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/testing/login', ['identifier' => 'user@example.com'])->assertOk();
        }

        $this->postJson('/api/v1/testing/login', ['identifier' => 'user@example.com'])->assertTooManyRequests();
        $this->postJson('/api/v1/testing/password-reset', ['contact' => 'user@example.com'])->assertOk();
    }

    public function test_no_limiter_key_contains_the_email_or_the_phone_number(): void
    {
        foreach (['login', 'otp-send', 'otp-verify', 'password-reset', 'register'] as $limiter) {
            $keys = collect(RateLimiter::limiter($limiter)(Request::create('/', 'POST', [
                'identifier' => 'private.person@example.com',
                'contact' => 'private.person@example.com',
                'email' => 'private.person@example.com',
                'phone' => '+22241111111',
            ])))->map(fn (Limit $limit): string => $limit->key);

            $ipOnly = count(RateLimiter::limiter($limiter)(Request::create('/', 'POST')));
            $this->assertGreaterThan($ipOnly, $keys->count(), "The [$limiter] limiter must key on the contact.");
            $keys->each(function (string $key): void {
                $this->assertStringNotContainsString('private.person', $key);
                $this->assertStringNotContainsString('22241111111', $key);
            });
        }
    }

    public function test_the_contact_hash_of_the_limiters_is_not_the_plain_app_key_hmac(): void
    {
        $plainAppKeyHmac = hash_hmac('sha256', 'private.person@example.com', (string) config('app.key'));

        $keys = collect(RateLimiter::limiter('login')(Request::create('/', 'POST', ['identifier' => 'private.person@example.com'])))
            ->map(fn (Limit $limit): string => $limit->key);

        $keys->each(fn (string $key) => $this->assertStringNotContainsString($plainAppKeyHmac, $key));
    }

    public function test_register_limits_the_email_and_the_phone_each(): void
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/testing/register', ['email' => 'user@example.com', 'phone' => "+2224000000$attempt"])->assertOk();
        }

        // Same email, new phone: blocked by the email bucket.
        $this->postJson('/api/v1/testing/register', ['email' => 'user@example.com', 'phone' => '+22249999999'])->assertTooManyRequests();

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->postJson('/api/v1/testing/register', ['email' => "other$attempt@example.com", 'phone' => '+22241111111'])->assertOk();
        }

        // Same phone, new email: blocked by the phone bucket.
        $this->postJson('/api/v1/testing/register', ['email' => 'fresh@example.com', 'phone' => '+22241111111'])->assertTooManyRequests();
    }

    public function test_password_update_is_keyed_by_the_account_and_does_not_share_the_login_counters(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'throttle:password-update'])->put('/api/v1/testing/password', fn () => ['ok' => true]);
        $alice = User::factory()->create(['email' => 'alice@example.com']);

        Sanctum::actingAs($alice);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->putJson('/api/v1/testing/password')->assertOk();
        }
        $this->putJson('/api/v1/testing/password')->assertTooManyRequests();

        // The sign-in of the same contact is not affected...
        $this->postJson('/api/v1/testing/login', ['identifier' => 'alice@example.com'])->assertOk();

        // ...and another account from the same IP is not either.
        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/v1/testing/password')->assertOk();
    }

    public function test_the_api_limit_is_counted_per_user_and_not_per_ip_once_authenticated(): void
    {
        Route::middleware(['api', 'throttle:api'])->get('/api/v1/testing/quota', fn () => ['ok' => true]);

        $guest = $this->getJson('/api/v1/testing/quota');
        $guest->assertHeader('X-RateLimit-Remaining', '119');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/testing/quota')->assertHeader('X-RateLimit-Remaining', '119');
        $this->getJson('/api/v1/testing/quota')->assertHeader('X-RateLimit-Remaining', '118');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/testing/quota')->assertHeader('X-RateLimit-Remaining', '119');
    }
}
