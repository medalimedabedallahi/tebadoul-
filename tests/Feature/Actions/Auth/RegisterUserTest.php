<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\RegisterUser;
use App\Enums\ContactPurpose;
use App\Enums\UserStatus;
use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class RegisterUserTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_registers_an_account_with_an_email_as_pending_verification(): void
    {
        $this->captureContactCodes();

        app(RegisterUser::class)->handle('Aminetou', '  Aminetou@Example.COM ', null, self::PASSWORD, 'ar');

        $user = User::query()->where('email', 'aminetou@example.com')->firstOrFail();
        $this->assertSame(UserStatus::PendingVerification, $user->status);
        $this->assertNull($user->phone);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('ar', $user->locale);
        $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
        $this->assertNotSame(self::PASSWORD, $user->password);
        Queue::assertPushed(SendContactCode::class, 1);
        $this->assertSame(ContactPurpose::ContactVerification, $this->activeVerification('aminetou@example.com')->purpose);
    }

    public function test_registers_an_account_with_a_phone_only_and_normalizes_the_number(): void
    {
        $this->captureContactCodes();

        app(RegisterUser::class)->handle('Moctar', null, '41 11 11 11', self::PASSWORD);

        $user = User::query()->where('phone', '+22241111111')->firstOrFail();
        $this->assertNull($user->email);
        $this->assertSame(UserStatus::PendingVerification, $user->status);
        $this->assertNotNull($this->activeVerification('+22241111111'));
        Queue::assertPushed(SendContactCode::class, 1);
    }

    public function test_sends_one_code_per_contact_when_both_are_given(): void
    {
        $this->captureContactCodes();

        app(RegisterUser::class)->handle('Fatimetou', 'f@example.com', '+22242222222', self::PASSWORD);

        Queue::assertPushed(SendContactCode::class, 2);
        $this->assertNotNull($this->activeVerification('f@example.com'));
        $this->assertNotNull($this->activeVerification('+22242222222'));
    }

    public function test_does_nothing_when_the_email_already_belongs_to_an_account(): void
    {
        $this->captureContactCodes();
        $existing = User::factory()->create(['email' => 'taken@example.com', 'name' => 'Owner']);

        app(RegisterUser::class)->handle('Intruder', 'TAKEN@example.com', null, self::PASSWORD);

        $this->assertSame(1, User::query()->count());
        $this->assertSame('Owner', $existing->fresh()->name);
        $this->assertSame(0, ContactVerification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_does_nothing_when_the_phone_already_belongs_to_an_account(): void
    {
        $this->captureContactCodes();
        User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22243333333']);

        app(RegisterUser::class)->handle('Intruder', 'new@example.com', '43 33 33 33', self::PASSWORD);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, ContactVerification::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_falls_back_to_the_application_locale_when_the_locale_is_not_supported(): void
    {
        $this->captureContactCodes();

        app(RegisterUser::class)->handle('Aminetou', 'a@example.com', null, self::PASSWORD, 'de');

        $this->assertSame('fr', User::query()->firstOrFail()->locale);
    }

    public function test_rejects_a_call_without_any_contact(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(RegisterUser::class)->handle('Nobody', null, ' ', self::PASSWORD);
    }

    public function test_does_nothing_when_only_one_of_the_two_contacts_is_taken(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'a@example.com']);

        app(RegisterUser::class)->handle('Twin', 'A@example.com', '+22244444444', self::PASSWORD);

        $this->assertNull(User::query()->where('phone', '+22244444444')->first());
        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'no contact at all' => [['name' => 'A', 'password' => self::PASSWORD], 'email'],
            'malformed email' => [['name' => 'A', 'email' => 'not-an-email', 'password' => self::PASSWORD], 'email'],
            'malformed phone' => [['name' => 'A', 'phone' => '12', 'password' => self::PASSWORD], 'phone'],
            'phone with letters' => [['name' => 'A', 'phone' => 'abcdefgh', 'password' => self::PASSWORD], 'phone'],
            'missing name' => [['email' => 'a@example.com', 'password' => self::PASSWORD], 'name'],
            'missing password' => [['name' => 'A', 'email' => 'a@example.com'], 'password'],
            'password of 11 characters' => [['name' => 'A', 'email' => 'a@example.com', 'password' => 'Short-pass1'], 'password'],
            'unsupported locale' => [['name' => 'A', 'email' => 'a@example.com', 'password' => self::PASSWORD, 'locale' => 'de'], 'locale'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    #[DataProvider('invalidInputs')]
    public function test_rules_reject_invalid_input(array $input, string $invalidField): void
    {
        $validator = Validator::make($input, RegisterUser::rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($invalidField, $validator->errors()->toArray());
    }

    public function test_rules_accept_a_national_phone_number_and_do_not_reveal_taken_contacts(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $validator = Validator::make(
            ['name' => 'A', 'email' => 'taken@example.com', 'phone' => '41 11 11 11', 'password' => self::PASSWORD],
            RegisterUser::rules(),
        );

        $this->assertTrue($validator->passes());
    }
}
