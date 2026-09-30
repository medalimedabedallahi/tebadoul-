<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\LoginUser;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class LoginUserTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_signs_in_with_an_email_regardless_of_its_spelling(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com', 'password' => self::PASSWORD]);

        $auth = app(LoginUser::class)->handle('  Aminetou@EXAMPLE.com ', self::PASSWORD, 'mobile');

        $this->assertTrue($auth->user->is($user));
        $this->assertSame([TokenAbility::AccessApi->value], $auth->abilities);
        $this->assertFalse($auth->isLimited());
        $this->assertMatchesRegularExpression('/^\d+\|bdl_/', $auth->plainTextToken);
    }

    public function test_signs_in_with_a_national_or_international_phone_number(): void
    {
        $user = User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22241111111', 'password' => self::PASSWORD]);

        foreach (['41 11 11 11', '+222 41111111', '0022241111111'] as $identifier) {
            $this->assertTrue(app(LoginUser::class)->handle($identifier, self::PASSWORD)->user->is($user));
        }
    }

    public function test_issues_a_named_token_with_explicit_abilities_and_the_configured_expiration(): void
    {
        $this->freezeSecond();
        config(['sanctum.expiration' => 90]);
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $auth = app(LoginUser::class)->handle($user->email, self::PASSWORD, 'my-phone');

        $token = PersonalAccessToken::query()->sole();
        $this->assertSame('my-phone', $token->name);
        $this->assertSame(['access-api'], $token->abilities);
        $this->assertTrue($token->expires_at->equalTo(now()->addMinutes(90)));
        $this->assertTrue($auth->expiresAt->equalTo(now()->addMinutes(90)));
    }

    public function test_an_account_pending_verification_receives_no_token(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification, 'password' => self::PASSWORD]);

        try {
            app(LoginUser::class)->handle($user->email, self::PASSWORD);
            $this->fail('An account pending verification must not sign in.');
        } catch (InvalidCredentialsException) {
            $this->assertSame(0, PersonalAccessToken::query()->count());
        }
    }

    public function test_rejects_a_wrong_password_and_issues_no_token(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        $this->expectException(InvalidCredentialsException::class);

        try {
            app(LoginUser::class)->handle('a@example.com', 'wrong-password-123');
        } finally {
            $this->assertSame(0, PersonalAccessToken::query()->count());
        }
    }

    public function test_an_unknown_identifier_fails_exactly_like_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);
        $failures = [];

        foreach ([['a@example.com', 'wrong-password-123'], ['ghost@example.com', self::PASSWORD], ['+22249999999', self::PASSWORD], ['!!', self::PASSWORD]] as [$identifier, $password]) {
            try {
                app(LoginUser::class)->handle($identifier, $password);
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

        app(LoginUser::class)->handle('ghost@example.com', self::PASSWORD);
    }

    public function test_refuses_a_suspended_account_only_after_the_password_is_verified(): void
    {
        User::factory()->suspended()->create(['email' => 's@example.com', 'password' => self::PASSWORD]);

        try {
            app(LoginUser::class)->handle('s@example.com', 'wrong-password-123');
            $this->fail('A wrong password must be refused.');
        } catch (InvalidCredentialsException) {
            $this->assertSame(0, PersonalAccessToken::query()->count());
        }

        $this->expectException(AccountSuspendedException::class);

        app(LoginUser::class)->handle('s@example.com', self::PASSWORD);
    }

    public function test_a_suspended_account_gets_no_token(): void
    {
        User::factory()->suspended()->create(['email' => 's@example.com', 'password' => self::PASSWORD]);

        try {
            app(LoginUser::class)->handle('s@example.com', self::PASSWORD);
        } catch (AccountSuspendedException) {
            //
        }

        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_rules_require_an_identifier_that_is_an_email_or_a_phone(): void
    {
        $this->assertTrue(Validator::make(['identifier' => 'a@example.com', 'password' => 'x'], LoginUser::rules())->passes());
        $this->assertTrue(Validator::make(['identifier' => '41 11 11 11', 'password' => 'x'], LoginUser::rules())->passes());
        $this->assertTrue(Validator::make(['identifier' => 'nonsense', 'password' => 'x'], LoginUser::rules())->fails());
        $this->assertTrue(Validator::make(['identifier' => 'a@example.com'], LoginUser::rules())->fails());
    }
}
