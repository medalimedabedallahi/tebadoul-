<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\RequestPasswordReset;
use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\SendContactVerification;
use App\Enums\ContactPurpose;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    private const NEW_PASSWORD = 'Brand-new-passphrase-77';

    public function test_does_not_issue_a_reset_code_to_a_phone_only_account(): void
    {
        $this->captureContactCodes();
        User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22241111111', 'password' => self::PASSWORD]);

        app(RequestPasswordReset::class)->handle('41 11 11 11');

        Queue::assertNothingPushed();
        $this->assertSame(0, ContactVerification::query()->count());
    }

    public function test_resets_the_password_of_an_email_account(): void
    {
        $this->captureContactCodes();
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        app(RequestPasswordReset::class)->handle('A@example.com');
        app(ResetPassword::class)->handle('a@example.com', $this->lastCode(), self::NEW_PASSWORD);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_revokes_every_token_of_the_account_and_only_of_that_account(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        $other = User::factory()->create();
        $user->createToken('phone');
        $user->createToken('laptop');
        $other->createToken('other');
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);

        app(ResetPassword::class)->handle('a@example.com', '123456', self::NEW_PASSWORD);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->assertSame(1, PersonalAccessToken::query()->count());
    }

    public function test_the_code_can_only_be_used_once(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);
        app(ResetPassword::class)->handle('a@example.com', '123456', self::NEW_PASSWORD);

        $this->expectException(InvalidVerificationCodeException::class);

        app(ResetPassword::class)->handle('a@example.com', '123456', 'Another-passphrase-88');
    }

    public function test_a_wrong_code_changes_nothing_and_keeps_the_tokens(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);
        $user->createToken('phone');
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);

        try {
            app(ResetPassword::class)->handle('a@example.com', '000000', self::NEW_PASSWORD);
            $this->fail('A wrong code must be refused.');
        } catch (InvalidVerificationCodeException) {
            $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
            $this->assertSame(1, $user->tokens()->count());
            $this->assertSame(1, ContactVerification::query()->sole()->attempts);
        }
    }

    public function test_the_code_locks_after_too_many_wrong_guesses(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com', 'max_attempts' => 2]);

        foreach (['000000', '111111', '123456'] as $code) {
            try {
                app(ResetPassword::class)->handle('a@example.com', $code, self::NEW_PASSWORD);
                $this->fail('The code must be refused.');
            } catch (InvalidVerificationCodeException) {
                //
            }
        }

        $this->assertSame(2, ContactVerification::query()->sole()->attempts);
    }

    public function test_rejects_an_expired_code(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->forPasswordReset()->expired()->create(['contact' => 'a@example.com']);

        $this->expectException(InvalidVerificationCodeException::class);

        app(ResetPassword::class)->handle('a@example.com', '123456', self::NEW_PASSWORD);
    }

    public function test_a_contact_verification_code_cannot_reset_a_password(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);

        try {
            app(ResetPassword::class)->handle('a@example.com', '123456', self::NEW_PASSWORD);
            $this->fail('A verification code must not reset a password.');
        } catch (InvalidVerificationCodeException) {
            $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        }
    }

    public function test_a_suspended_account_cannot_reset_its_password(): void
    {
        $user = User::factory()->suspended()->create(['email' => 's@example.com', 'password' => self::PASSWORD]);
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 's@example.com']);

        try {
            app(ResetPassword::class)->handle('s@example.com', '123456', self::NEW_PASSWORD);
            $this->fail('A suspended account must be refused.');
        } catch (InvalidVerificationCodeException) {
            $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        }
    }

    public function test_requesting_a_reset_for_an_unknown_contact_does_nothing(): void
    {
        $this->captureContactCodes();

        app(RequestPasswordReset::class)->handle('ghost@example.com');

        $this->assertSame(0, ContactVerification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_requesting_a_reset_sends_a_code_even_to_a_verified_contact(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'a@example.com']);

        app(RequestPasswordReset::class)->handle('a@example.com');

        Queue::assertPushed(SendContactCode::class, 1);
        $this->assertSame(ContactPurpose::PasswordReset, $this->activeVerification('a@example.com')->purpose);
    }

    public function test_no_reset_code_is_issued_for_an_unverified_contact(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        app(SendContactVerification::class)->handle('a@example.com');
        app(RequestPasswordReset::class)->handle('a@example.com');

        $this->assertSame(ContactPurpose::ContactVerification, ContactVerification::query()->unconsumed()->sole()->purpose);
    }

    public function test_an_unverified_contact_cannot_reset_the_password_even_with_a_valid_reset_code(): void
    {
        $user = User::factory()->phoneVerified()->unverified()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);

        try {
            app(ResetPassword::class)->handle('a@example.com', '123456', self::NEW_PASSWORD);
            $this->fail('An unverified contact must not reset a password.');
        } catch (InvalidVerificationCodeException) {
            $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
        }
    }

    public function test_a_reset_keeps_the_optional_phone_number(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'phone' => '+22241111111', 'phone_verified_at' => null]);
        ContactVerification::factory()->for($user)->forPhone('+22241111111')->create();
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);

        app(ResetPassword::class)->handle('a@example.com', '123456', self::NEW_PASSWORD);

        $user->refresh();
        $this->assertSame('a@example.com', $user->email);
        $this->assertSame('+22241111111', $user->phone);
        $this->assertSame(1, ContactVerification::query()->unconsumed()->count());
    }

    public function test_rules_enforce_the_password_policy_and_the_code_format(): void
    {
        $valid = ['contact' => 'a@example.com', 'code' => '123456', 'password' => self::NEW_PASSWORD];

        $this->assertTrue(Validator::make($valid, ResetPassword::rules())->passes());
        $this->assertTrue(Validator::make([...$valid, 'password' => 'short'], ResetPassword::rules())->fails());
        $this->assertTrue(Validator::make([...$valid, 'code' => 'abcdef'], ResetPassword::rules())->fails());
        $this->assertTrue(Validator::make([...$valid, 'contact' => 'nonsense'], ResetPassword::rules())->fails());
        $this->assertTrue(Validator::make(['contact' => 'a@example.com'], RequestPasswordReset::rules())->passes());
    }
}
