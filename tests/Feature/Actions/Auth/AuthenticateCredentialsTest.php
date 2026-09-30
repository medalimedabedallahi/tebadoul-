<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\AuthenticateCredentials;
use App\Enums\UserStatus;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class AuthenticateCredentialsTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_returns_the_account_for_an_email_regardless_of_its_spelling(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);

        $found = app(AuthenticateCredentials::class)->handle('  Aminetou@EXAMPLE.com ', self::PASSWORD);

        $this->assertTrue($found->is($user));
    }

    public function test_returns_the_account_for_a_national_or_international_phone_number(): void
    {
        $user = User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22241111111', 'password' => self::PASSWORD]);

        foreach (['41 11 11 11', '+222 41111111', '0022241111111'] as $identifier) {
            $this->assertTrue(app(AuthenticateCredentials::class)->handle($identifier, self::PASSWORD)->is($user));
        }
    }

    public function test_refuses_a_pending_verification_account_exactly_like_an_unknown_identifier(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification, 'password' => self::PASSWORD]);
        $failures = [];

        foreach ([$user->email, 'ghost@example.com'] as $identifier) {
            try {
                app(AuthenticateCredentials::class)->handle($identifier, self::PASSWORD);
            } catch (InvalidCredentialsException $e) {
                $failures[] = [$e::class, $e->getMessage(), $e->getCode()];
            }
        }

        $this->assertCount(2, $failures);
        $this->assertCount(1, array_unique($failures, SORT_REGULAR));
    }

    public function test_refuses_an_unverified_contact_of_an_active_account(): void
    {
        User::factory()->phoneVerified()->unverified()->create(['email' => 'secondary@example.com', 'password' => self::PASSWORD]);

        $this->expectException(InvalidCredentialsException::class);

        app(AuthenticateCredentials::class)->handle('secondary@example.com', self::PASSWORD);
    }

    public function test_rejects_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        $this->expectException(InvalidCredentialsException::class);

        app(AuthenticateCredentials::class)->handle('a@example.com', 'wrong-password-123');
    }

    public function test_an_unknown_identifier_fails_exactly_like_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);
        $failures = [];

        foreach ([['a@example.com', 'wrong-password-123'], ['ghost@example.com', self::PASSWORD], ['+22249999999', self::PASSWORD], ['!!', self::PASSWORD]] as [$identifier, $password]) {
            try {
                app(AuthenticateCredentials::class)->handle($identifier, $password);
            } catch (InvalidCredentialsException $e) {
                $failures[] = [$e::class, $e->getMessage(), $e->getCode()];
            }
        }

        $this->assertCount(4, $failures);
        $this->assertCount(1, array_unique($failures, SORT_REGULAR));
    }

    public function test_an_unknown_identifier_still_costs_a_password_check(): void
    {
        Hash::shouldReceive('make')->atMost()->once()->andReturn('$2y$04$'.str_repeat('a', 53));
        Hash::shouldReceive('check')->once()->andReturn(false);

        $this->expectException(InvalidCredentialsException::class);

        app(AuthenticateCredentials::class)->handle('ghost@example.com', self::PASSWORD);
    }

    public function test_refuses_a_suspended_account_only_after_the_password_is_verified(): void
    {
        User::factory()->suspended()->create(['email' => 's@example.com', 'password' => self::PASSWORD]);

        try {
            app(AuthenticateCredentials::class)->handle('s@example.com', 'wrong-password-123');
            $this->fail('A wrong password must be refused.');
        } catch (InvalidCredentialsException) {
            //
        }

        $this->expectException(AccountSuspendedException::class);

        app(AuthenticateCredentials::class)->handle('s@example.com', self::PASSWORD);
    }

    public function test_rules_require_an_identifier_that_is_an_email_or_a_phone(): void
    {
        $this->assertTrue(Validator::make(['identifier' => 'a@example.com', 'password' => 'x'], AuthenticateCredentials::rules())->passes());
        $this->assertTrue(Validator::make(['identifier' => '41 11 11 11', 'password' => 'x'], AuthenticateCredentials::rules())->passes());
        $this->assertTrue(Validator::make(['identifier' => 'nonsense', 'password' => 'x'], AuthenticateCredentials::rules())->fails());
        $this->assertTrue(Validator::make(['identifier' => 'a@example.com'], AuthenticateCredentials::rules())->fails());
    }
}
