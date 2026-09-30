<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class LoginEndpointTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    private const URL = '/api/v1/auth/login';

    public function test_signs_in_with_an_email_and_returns_a_bearer_token_and_the_masked_profile(): void
    {
        $user = User::factory()->create(['email' => 'aminetou@example.com', 'name' => 'Aminetou', 'password' => self::PASSWORD]);

        $response = $this->postJson(self::URL, ['identifier' => 'Aminetou@example.com', 'password' => self::PASSWORD, 'device_name' => 'mobile']);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.abilities', ['access-api'])
            ->assertJsonPath('data.verification_required', false)
            ->assertJsonPath('data.user.public_id', $user->public_id)
            ->assertJsonPath('data.user.email_masked', 'a***@e***.com')
            ->assertJsonPath('data.user.status', 'active');
        $this->assertMatchesRegularExpression('/^\d+\|bdl_/', $response->json('data.token'));
        $this->assertNotNull($response->json('data.expires_at'));
        $this->assertSame('mobile', PersonalAccessToken::query()->sole()->name);
    }

    public function test_the_issued_token_authenticates_the_next_request(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);
        $token = $this->postJson(self::URL, ['identifier' => 'a@example.com', 'password' => self::PASSWORD])->json('data.token');

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_signs_in_with_a_phone_number(): void
    {
        User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22241111111', 'password' => self::PASSWORD]);

        $this->postJson(self::URL, ['identifier' => '41 11 11 11', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.user.phone_masked', '+222*****11')
            ->assertJsonPath('data.user.email_masked', null);
    }

    public function test_an_account_pending_verification_gets_exactly_the_401_of_an_unknown_identifier(): void
    {
        User::factory()->unverified()->create(['email' => 'p@example.com', 'status' => UserStatus::PendingVerification, 'password' => self::PASSWORD]);

        $pending = $this->postJson(self::URL, ['identifier' => 'p@example.com', 'password' => self::PASSWORD]);
        $unknown = $this->postJson(self::URL, ['identifier' => 'ghost@example.com', 'password' => self::PASSWORD]);

        $pending->assertUnauthorized()->assertJsonPath('code', 'invalid_credentials');
        $this->assertSame($unknown->getStatusCode(), $pending->getStatusCode());
        $this->assertSame(
            collect($unknown->json())->except('request_id')->all(),
            collect($pending->json())->except('request_id')->all(),
        );
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_an_unverified_contact_of_an_active_account_cannot_be_used_to_sign_in(): void
    {
        User::factory()->phoneVerified()->unverified()->create(['email' => 'secondary@example.com', 'phone' => '+22241111111', 'password' => self::PASSWORD]);

        $this->postJson(self::URL, ['identifier' => 'secondary@example.com', 'password' => self::PASSWORD])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
        $this->postJson(self::URL, ['identifier' => '41111111', 'password' => self::PASSWORD])->assertOk();
    }

    public function test_an_unknown_identifier_and_a_wrong_password_get_the_same_401(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        $wrongPassword = $this->postJson(self::URL, ['identifier' => 'a@example.com', 'password' => 'wrong-password-123']);
        $unknown = $this->postJson(self::URL, ['identifier' => 'ghost@example.com', 'password' => 'wrong-password-123']);

        $wrongPassword->assertUnauthorized()->assertJsonPath('code', 'invalid_credentials');
        $unknown->assertUnauthorized();
        $this->assertSame(
            collect($wrongPassword->json())->except('request_id')->all(),
            collect($unknown->json())->except('request_id')->all(),
        );
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_suspended_account_gets_403_once_the_password_is_right(): void
    {
        User::factory()->suspended()->create(['email' => 's@example.com', 'password' => self::PASSWORD]);

        $this->postJson(self::URL, ['identifier' => 's@example.com', 'password' => 'wrong-password-123'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
        $this->postJson(self::URL, ['identifier' => 's@example.com', 'password' => self::PASSWORD])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_suspended');
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_the_401_message_is_translated(): void
    {
        $this->postJson(self::URL, ['identifier' => 'ghost@example.com', 'password' => 'whatever-123'], ['Accept-Language' => 'ar'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'بيانات الاعتماد هذه غير مطابقة لسجلاتنا.');
    }

    public function test_rejects_a_missing_or_malformed_identifier(): void
    {
        $this->postJson(self::URL, [])->assertUnprocessable()->assertJsonValidationErrors(['identifier', 'password']);
        $this->postJson(self::URL, ['identifier' => 'nonsense', 'password' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['identifier']);
    }
}
