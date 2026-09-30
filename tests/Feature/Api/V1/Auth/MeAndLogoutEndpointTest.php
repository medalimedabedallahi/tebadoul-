<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class MeAndLogoutEndpointTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    public function test_me_returns_the_masked_profile_of_the_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Aminetou', 'email' => 'a@example.com']);
        $token = $user->createToken('mobile', ['access-api'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.public_id', $user->public_id)
            ->assertJsonPath('data.name', 'Aminetou')
            ->assertJsonPath('data.email_masked', 'a***@e***.com')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.password');
    }

    public function test_a_legacy_limited_token_of_a_pending_account_is_refused_with_403(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification]);
        $token = $user->createToken('mobile', [TokenAbility::VerifyContact->value])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonMissingPath('data');
    }

    public function test_a_token_of_a_suspended_account_is_refused_with_403_on_every_authenticated_route(): void
    {
        $user = User::factory()->suspended()->create(['password' => self::PASSWORD]);
        $token = $user->createToken('mobile', [TokenAbility::AccessApi->value])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertForbidden();
        $this->withToken($token)
            ->putJson('/api/v1/auth/password', ['current_password' => self::PASSWORD, 'password' => 'Brand-new-passphrase-77'])
            ->assertForbidden();
        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }

    public function test_a_web_session_of_an_account_that_is_not_active_is_refused_with_403_on_the_api(): void
    {
        foreach ([User::factory()->suspended()->create(), User::factory()->unverified()->create(['status' => UserStatus::PendingVerification])] as $user) {
            $this->forgetResolvedUser();

            $this->actingAs($user, 'web')
                ->getJson('/api/v1/auth/me')
                ->assertForbidden()
                ->assertJsonPath('code', 'forbidden');
        }
    }

    public function test_a_web_session_of_an_active_account_reaches_the_api(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_me_returns_401_without_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }

    public function test_me_returns_401_with_an_expired_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile', ['access-api'], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_revokes_the_current_token_only_and_returns_204(): void
    {
        $user = User::factory()->create();
        $kept = $user->createToken('laptop')->plainTextToken;
        $revoked = $user->createToken('mobile')->plainTextToken;

        $this->withToken($revoked)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(['laptop'], PersonalAccessToken::query()->pluck('name')->all());
        $this->forgetResolvedUser();
        $this->withToken($kept)->getJson('/api/v1/auth/me')->assertOk();
        $this->forgetResolvedUser();
        $this->withToken($revoked)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_refuses_a_legacy_limited_token_of_a_pending_account(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::PendingVerification]);
        $token = $user->createToken('mobile', [TokenAbility::VerifyContact->value])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertForbidden();
    }

    public function test_logout_returns_401_without_a_token(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }
}
