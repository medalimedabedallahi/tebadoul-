<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Jobs\SendContactCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class RegisterEndpointTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    private const URL = '/api/v1/auth/register';

    public function test_registers_with_an_email_and_returns_202_without_any_account_data(): void
    {
        $this->captureContactCodes();

        $response = $this->postJson(self::URL, ['name' => 'Aminetou', 'email' => 'a@example.com', 'password' => self::PASSWORD, 'accept_terms' => true]);

        $response->assertAccepted()->assertJsonStructure(['data' => ['message']]);
        $this->assertSame(['data'], array_keys($response->json()));
        $this->assertSame(['message'], array_keys($response->json('data')));
        $this->assertSame(UserStatus::PendingVerification, User::query()->sole()->status);
        Queue::assertPushed(SendContactCode::class, 1);
    }

    public function test_registration_records_when_and_which_version_of_the_terms_was_accepted(): void
    {
        $this->captureContactCodes();
        $this->freezeSecond();

        $this->postJson(self::URL, ['name' => 'Aminetou', 'email' => 'a@example.com', 'password' => self::PASSWORD, 'accept_terms' => true])
            ->assertAccepted();

        $user = User::query()->sole();
        $this->assertTrue(now()->equalTo($user->terms_accepted_at));
        $this->assertSame(config('legal.version'), $user->terms_version);
    }

    public function test_registration_without_accepting_the_terms_is_refused_with_422(): void
    {
        $this->postJson(self::URL, ['name' => 'Aminetou', 'email' => 'a@example.com', 'password' => self::PASSWORD, 'accept_terms' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accept_terms' => 'Le champ acceptation des conditions doit être accepté.']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_registers_with_a_phone_only(): void
    {
        $this->captureContactCodes();

        $this->postJson(self::URL, ['name' => 'Moctar', 'phone' => '41 11 11 11', 'password' => self::PASSWORD, 'accept_terms' => true])->assertAccepted();

        $this->assertSame('+22241111111', User::query()->sole()->phone);
        Queue::assertPushed(SendContactCode::class, 1);
    }

    public function test_an_already_registered_contact_gets_the_identical_response_and_nothing_changes(): void
    {
        $this->captureContactCodes();
        User::factory()->create(['email' => 'taken@example.com']);

        $fresh = $this->postJson(self::URL, ['name' => 'New', 'email' => 'new@example.com', 'password' => self::PASSWORD, 'accept_terms' => true]);
        $duplicate = $this->postJson(self::URL, ['name' => 'Dup', 'email' => 'Taken@Example.com', 'password' => self::PASSWORD, 'accept_terms' => true]);

        $duplicate->assertAccepted();
        $this->assertSame($fresh->getStatusCode(), $duplicate->getStatusCode());
        $this->assertSame($this->withoutRequestId($fresh), $this->withoutRequestId($duplicate));
        $this->assertSame(2, User::query()->count());
        Queue::assertPushed(SendContactCode::class, 1);
    }

    public function test_the_response_is_localized_and_stable_in_arabic(): void
    {
        $this->captureContactCodes();

        $this->postJson(self::URL, ['name' => 'A', 'email' => 'a@example.com', 'password' => self::PASSWORD, 'accept_terms' => true], ['Accept-Language' => 'ar'])
            ->assertAccepted()
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('data.message', 'إذا كانت المعلومات المقدمة صحيحة، فقد تم إرسال رمز التحقق.');
    }

    public function test_a_contact_held_unverified_by_a_pending_account_is_taken_over_and_the_orphan_is_deleted(): void
    {
        $this->captureContactCodes();
        $squatter = User::factory()->unverified()->create(['name' => 'Squatter', 'email' => 'victime@example.com', 'status' => UserStatus::PendingVerification]);
        $squatter->createToken('legacy');

        $this->postJson(self::URL, ['name' => 'Vraie Victime', 'email' => 'Victime@example.com', 'password' => self::PASSWORD, 'accept_terms' => true])->assertAccepted();

        $this->assertModelMissing($squatter);
        $this->assertSame('Vraie Victime', User::query()->where('email', 'victime@example.com')->sole()->name);
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_a_contact_held_unverified_by_another_account_is_detached_but_its_verified_contact_is_kept(): void
    {
        $this->captureContactCodes();
        $holder = User::factory()->phoneVerified()->unverified()->create(['email' => 'shared@example.com', 'phone' => '+22241111111']);

        $this->postJson(self::URL, ['name' => 'Owner', 'email' => 'shared@example.com', 'password' => self::PASSWORD, 'accept_terms' => true])->assertAccepted();

        $holder->refresh();
        $this->assertNull($holder->email);
        $this->assertSame('+22241111111', $holder->phone);
        $this->assertNotNull($holder->phone_verified_at);
        $this->assertSame('Owner', User::query()->where('email', 'shared@example.com')->sole()->name);
    }

    public function test_a_contact_verified_by_another_account_is_never_touched(): void
    {
        $this->captureContactCodes();
        $owner = User::factory()->phoneVerified()->create(['email' => null, 'phone' => '+22241111111', 'name' => 'Owner']);

        $this->postJson(self::URL, ['name' => 'Intruder', 'email' => 'intruder@example.com', 'phone' => '41111111', 'password' => self::PASSWORD, 'accept_terms' => true])
            ->assertAccepted();

        $this->assertSame('+22241111111', $owner->fresh()->phone);
        $this->assertSame(1, User::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_rejects_an_email_with_non_ascii_characters(): void
    {
        $this->postJson(self::URL, ['name' => 'A', 'email' => 'élodie@exemple.com', 'password' => self::PASSWORD, 'accept_terms' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_a_retry_with_the_same_idempotency_key_creates_one_account_and_replays_the_response(): void
    {
        $this->captureContactCodes();
        $payload = ['name' => 'A', 'email' => 'a@example.com', 'password' => self::PASSWORD, 'accept_terms' => true];

        $first = $this->postJson(self::URL, $payload, ['Idempotency-Key' => 'register-0001-abcd']);
        $second = $this->postJson(self::URL, $payload, ['Idempotency-Key' => 'register-0001-abcd']);

        $first->assertAccepted();
        $second->assertAccepted()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame(1, User::query()->count());
        Queue::assertPushed(SendContactCode::class, 1);
    }

    public function test_validation_errors_use_the_uniform_body_and_never_mention_existing_accounts(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(self::URL, ['name' => 'Dup', 'email' => 'taken@example.com', 'password' => 'short', 'accept_terms' => true])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['password'])
            ->assertJsonMissingValidationErrors(['email']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'empty payload' => [[], 'name'],
            'no contact' => [['name' => 'A', 'password' => self::PASSWORD, 'accept_terms' => true], 'email'],
            'malformed phone' => [['name' => 'A', 'phone' => '12', 'password' => self::PASSWORD, 'accept_terms' => true], 'phone'],
            'password of 11 characters' => [['name' => 'A', 'email' => 'a@example.com', 'password' => 'Short-pass1', 'accept_terms' => true], 'password'],
            'array instead of string' => [['name' => ['x'], 'email' => 'a@example.com', 'password' => self::PASSWORD, 'accept_terms' => true], 'name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_rejects_an_invalid_payload_with_422(array $payload, string $field): void
    {
        $this->postJson(self::URL, $payload)->assertUnprocessable()->assertJsonValidationErrors([$field]);

        $this->assertSame(0, User::query()->count());
    }

    public function test_the_phone_error_message_is_readable_in_french(): void
    {
        $this->postJson(self::URL, ['name' => 'A', 'phone' => '12', 'password' => self::PASSWORD, 'accept_terms' => true])
            ->assertJsonPath('errors.phone.0', 'Le champ téléphone doit être un numéro de téléphone valide.');
    }

    /**
     * @return array<string, mixed>
     */
    private function withoutRequestId(TestResponse $response): array
    {
        return collect($response->json())->except('request_id')->all();
    }
}
