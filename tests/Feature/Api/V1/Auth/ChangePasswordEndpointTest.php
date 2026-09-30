<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ChangePasswordEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-horse-2026-battery';

    private const NEW_PASSWORD = 'Brand-new-passphrase-77';

    public function test_changes_the_password_with_a_full_access_token(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $token = $user->createToken('mobile', [TokenAbility::AccessApi->value])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/auth/password', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD])
            ->assertNoContent();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_returns_403_with_only_the_limited_verify_contact_ability(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification, 'password' => self::PASSWORD]);
        $token = $user->createToken('mobile', [TokenAbility::VerifyContact->value])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/auth/password', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_returns_401_without_a_token(): void
    {
        $this->putJson('/api/v1/auth/password', ['current_password' => self::PASSWORD, 'password' => self::NEW_PASSWORD])
            ->assertUnauthorized();
    }

    public function test_returns_422_for_a_wrong_current_password_and_keeps_the_tokens(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $token = $user->createToken('mobile', [TokenAbility::AccessApi->value])->plainTextToken;

        $this->withToken($token)
            ->putJson('/api/v1/auth/password', ['current_password' => 'wrong-password-123', 'password' => self::NEW_PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);

        $this->assertSame(1, PersonalAccessToken::query()->count());
    }
}
