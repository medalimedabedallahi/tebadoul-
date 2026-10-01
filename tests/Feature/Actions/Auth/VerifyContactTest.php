<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\SendContactVerification;
use App\Actions\Auth\VerifyContact;
use App\Enums\UserStatus;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class VerifyContactTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_verifies_an_email_and_activates_the_account(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com', 'status' => UserStatus::PendingVerification]);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);

        $verified = app(VerifyContact::class)->handle(' A@Example.com ', '123456');

        $this->assertTrue($verified->is($user));
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->phone_verified_at);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull(ContactVerification::query()->sole()->consumed_at);
    }

    public function test_refuses_to_verify_a_phone_number(): void
    {
        $user = User::factory()->create(['email' => null, 'phone' => '+22241111111', 'email_verified_at' => null, 'status' => UserStatus::PendingVerification]);
        ContactVerification::factory()->for($user)->forPhone('+22241111111')->create();

        try {
            app(VerifyContact::class)->handle('41 11 11 11', '123456');
            $this->fail('A phone verification code must be refused.');
        } catch (InvalidVerificationCodeException) {
            $user->refresh();
            $this->assertNull($user->phone_verified_at);
            $this->assertSame(UserStatus::PendingVerification, $user->status);
        }
    }

    public function test_a_suspended_account_stays_suspended(): void
    {
        $user = User::factory()->suspended()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);

        app(VerifyContact::class)->handle('a@example.com', '123456');

        $this->assertSame(UserStatus::Suspended, $user->fresh()->status);
    }

    public function test_the_code_can_only_be_used_once(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);
        app(VerifyContact::class)->handle('a@example.com', '123456');

        $this->expectException(InvalidVerificationCodeException::class);

        app(VerifyContact::class)->handle('a@example.com', '123456');
    }

    public function test_rejects_an_expired_code(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->expired()->create(['contact' => 'a@example.com']);

        try {
            app(VerifyContact::class)->handle('a@example.com', '123456');
            $this->fail('An expired code must be refused.');
        } catch (InvalidVerificationCodeException) {
            $this->assertNull($user->fresh()->email_verified_at);
        }
    }

    public function test_a_code_expires_after_the_configured_lifetime(): void
    {
        $this->captureContactCodes();
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        app(SendContactVerification::class)->handle('a@example.com');
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->expectException(InvalidVerificationCodeException::class);

        app(VerifyContact::class)->handle('a@example.com', $code);
    }

    public function test_a_code_is_still_valid_just_before_its_expiry(): void
    {
        $this->captureContactCodes();
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        app(SendContactVerification::class)->handle('a@example.com');
        $code = $this->lastCode();

        $this->travel(9)->minutes();

        $this->assertTrue(app(VerifyContact::class)->handle('a@example.com', $code)->is($user));
    }

    public function test_wrong_guesses_are_counted_and_the_code_locks_after_the_maximum(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com', 'max_attempts' => 3]);

        foreach (range(1, 3) as $attempt) {
            try {
                app(VerifyContact::class)->handle('a@example.com', '000000');
                $this->fail('A wrong code must be refused.');
            } catch (InvalidVerificationCodeException) {
                $this->assertSame($attempt, ContactVerification::query()->sole()->attempts);
            }
        }

        try {
            app(VerifyContact::class)->handle('a@example.com', '123456');
            $this->fail('A locked code must be refused even when it is right.');
        } catch (InvalidVerificationCodeException) {
            $this->assertNull($user->fresh()->email_verified_at);
            $this->assertSame(3, ContactVerification::query()->sole()->attempts);
        }
    }

    public function test_requesting_a_new_code_unlocks_the_contact(): void
    {
        $this->captureContactCodes();
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->locked()->create(['contact' => 'a@example.com']);

        app(SendContactVerification::class)->handle('a@example.com');

        $this->assertTrue(app(VerifyContact::class)->handle('a@example.com', $this->lastCode())->is($user));
    }

    public function test_a_superseded_code_no_longer_works(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'a@example.com']);
        app(SendContactVerification::class)->handle('a@example.com');
        $oldCode = $this->lastCode();
        app(SendContactVerification::class)->handle('a@example.com');
        $newCode = $this->lastCode();

        try {
            app(VerifyContact::class)->handle('a@example.com', $oldCode);
            $this->fail('The previous code must be refused.');
        } catch (InvalidVerificationCodeException) {
            //
        }

        $this->assertInstanceOf(User::class, app(VerifyContact::class)->handle('a@example.com', $newCode));
    }

    public function test_a_password_reset_code_cannot_verify_a_contact(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);

        $this->expectException(InvalidVerificationCodeException::class);

        app(VerifyContact::class)->handle('a@example.com', '123456');
    }

    public function test_a_code_cannot_be_used_for_another_contact(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);
        User::factory()->unverified()->create(['email' => 'b@example.com']);

        $this->expectException(InvalidVerificationCodeException::class);

        app(VerifyContact::class)->handle('b@example.com', '123456');
    }

    public function test_an_unknown_contact_fails_like_a_wrong_code(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);
        $failures = [];

        foreach ([['a@example.com', '999999'], ['ghost@example.com', '123456'], ['+22249999999', '123456'], ['!!', '123456']] as [$contact, $code]) {
            try {
                app(VerifyContact::class)->handle($contact, $code);
            } catch (InvalidVerificationCodeException $e) {
                $failures[] = [$e::class, $e->getMessage()];
            }
        }

        $this->assertCount(4, $failures);
        $this->assertCount(1, array_unique($failures, SORT_REGULAR));
    }

    public function test_rules_require_a_contact_and_a_numeric_code(): void
    {
        $this->assertTrue(Validator::make(['contact' => 'a@example.com', 'code' => '012345'], VerifyContact::rules())->passes());
        $this->assertTrue(Validator::make(['contact' => 'a@example.com', 'code' => '12ab56'], VerifyContact::rules())->fails());
        $this->assertTrue(Validator::make(['contact' => 'a@example.com', 'code' => '123'], VerifyContact::rules())->fails());
        $this->assertTrue(Validator::make(['code' => '123456'], VerifyContact::rules())->fails());
    }
}
