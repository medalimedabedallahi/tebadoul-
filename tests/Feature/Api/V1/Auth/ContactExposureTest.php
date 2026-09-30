<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

/**
 * Cross-cutting: no response of the authentication API ever exposes an internal id, a contact in
 * clear text, a password hash or the code hash of a verification. Every route is exercised once.
 */
class ContactExposureTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const FORBIDDEN_STRINGS = ['a@example.com', '+22241111111', '41111111'];

    public function test_register_response_exposes_nothing_sensitive(): void
    {
        $this->captureContactCodes();

        $this->assertResponseClean(
            $this->postJson('/api/v1/auth/register', ['accept_terms' => true, 'name' => 'A', 'email' => 'a@example.com', 'phone' => '41 11 11 11', 'password' => self::PASSWORD])
        );
    }

    public function test_login_response_exposes_no_internal_id_or_clear_contact(): void
    {
        User::factory()->create(['email' => 'a@example.com', 'password' => self::PASSWORD]);

        $response = $this->postJson('/api/v1/auth/login', ['identifier' => 'a@example.com', 'password' => self::PASSWORD]);

        $this->assertResponseClean($response);
        $response->assertJsonMissingPath('data.user.id')->assertJsonMissingPath('data.id');
    }

    public function test_me_response_exposes_nothing_sensitive(): void
    {
        $user = User::factory()->create(['email' => 'a@example.com', 'phone' => null]);
        $token = $user->createToken('mobile', ['access-api'])->plainTextToken;

        $this->assertResponseClean($this->withToken($token)->getJson('/api/v1/auth/me'));
    }

    public function test_send_and_verify_responses_expose_nothing_sensitive(): void
    {
        $this->captureContactCodes();
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);

        $this->assertResponseClean($this->postJson('/api/v1/auth/contacts/verification/send', ['contact' => 'a@example.com']));

        $code = $this->lastCode();
        $this->assertResponseClean($this->postJson('/api/v1/auth/contacts/verification/verify', ['contact' => 'a@example.com', 'code' => $code]));
    }

    public function test_password_forgot_and_reset_responses_expose_nothing_sensitive(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'a@example.com']);

        $this->assertResponseClean($this->postJson('/api/v1/auth/password/forgot', ['contact' => 'a@example.com']));

        $code = $this->lastCode();
        $this->assertResponseClean(
            $this->postJson('/api/v1/auth/password/reset', ['contact' => 'a@example.com', 'code' => $code, 'password' => 'Brand-new-passphrase-77'])
        );
    }

    public function test_validation_error_responses_do_not_echo_back_the_submitted_contact_in_a_leaking_way(): void
    {
        // The field VALUE is not part of Laravel's validation error body; only the field NAME is.
        $response = $this->postJson('/api/v1/auth/register', ['accept_terms' => true, 'name' => 'A', 'email' => 'a@example.com', 'password' => 'short']);

        $response->assertUnprocessable();
        $this->assertStringNotContainsString('a@example.com', $response->getContent());
    }

    private function assertResponseClean(TestResponse $response): void
    {
        $content = $response->getContent();

        foreach (self::FORBIDDEN_STRINGS as $needle) {
            $this->assertStringNotContainsString($needle, (string) $content, "Response leaked [$needle].");
        }

        $this->assertStringNotContainsString('"email"', (string) $content);
        $this->assertStringNotContainsString('"phone"', (string) $content);
        $this->assertStringNotContainsString('"password"', (string) $content);
        $this->assertStringNotContainsString('code_hash', (string) $content);
    }
}
