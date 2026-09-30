<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class PasswordResetEndpointTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    private const FORGOT_URL = '/api/v1/auth/password/forgot';

    private const RESET_URL = '/api/v1/auth/password/reset';

    private const NEW_PASSWORD = 'Brand-new-passphrase-77';

    public function test_forgot_answers_202_with_the_same_body_whether_or_not_the_account_exists(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'a@example.com']);
        User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22241111111']);
        User::factory()->unverified()->create(['email' => 'unverified@example.com']);

        $answers = collect(['a@example.com', '41 11 11 11', 'ghost@example.com', '+22249999999', 'unverified@example.com'])
            ->map(fn (string $contact): array => collect($this->postJson(self::FORGOT_URL, ['contact' => $contact])->assertAccepted()->json())->except('request_id')->all());

        $this->assertCount(1, $answers->unique());
        $this->assertSame(['data' => ['message' => __('If the contact belongs to an account, a password reset code has been sent.')]], $answers->first());
        Queue::assertPushed(SendContactCode::class, 2);
    }

    public function test_forgot_rejects_a_malformed_contact(): void
    {
        $this->postJson(self::FORGOT_URL, ['contact' => 'nonsense'])->assertUnprocessable()->assertJsonValidationErrors(['contact']);
    }

    public function test_full_flow_reset_by_phone_only_then_old_password_and_tokens_are_dead(): void
    {
        $this->captureContactCodes();
        $user = User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22241111111', 'password' => self::PASSWORD]);
        $oldToken = $user->createToken('phone')->plainTextToken;

        $this->postJson(self::FORGOT_URL, ['contact' => '41111111'])->assertAccepted();
        $this->postJson(self::RESET_URL, ['contact' => '+22241111111', 'code' => $this->lastCode(), 'password' => self::NEW_PASSWORD])
            ->assertOk()
            ->assertExactJson(['data' => ['reset' => true]]);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->forgetResolvedUser();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['identifier' => '41111111', 'password' => self::PASSWORD])->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['identifier' => '41111111', 'password' => self::NEW_PASSWORD])->assertOk();
    }

    public function test_reset_answers_422_for_a_wrong_expired_or_unknown_code_with_the_same_body(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);
        ContactVerification::factory()->for($user)->forPasswordReset()->expired()->create(['contact' => 'old@example.com']);

        $answers = collect([['a@example.com', '999999'], ['ghost@example.com', '123456'], ['old@example.com', '123456']])
            ->map(fn (array $input): array => collect(
                $this->postJson(self::RESET_URL, ['contact' => $input[0], 'code' => $input[1], 'password' => self::NEW_PASSWORD])
                    ->assertUnprocessable()
                    ->assertJsonPath('code', 'invalid_verification_code')
                    ->json()
            )->except('request_id')->all());

        $this->assertCount(1, $answers->unique());
    }

    public function test_reset_validates_the_password_before_touching_the_code(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->forPasswordReset()->create(['contact' => 'a@example.com']);

        $this->postJson(self::RESET_URL, ['contact' => 'a@example.com', 'code' => '123456', 'password' => 'short'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['password']);

        $this->assertSame(0, ContactVerification::query()->sole()->attempts);
    }

    public function test_reset_requires_every_field(): void
    {
        $this->postJson(self::RESET_URL, [])->assertUnprocessable()->assertJsonValidationErrors(['contact', 'code', 'password']);
    }
}
