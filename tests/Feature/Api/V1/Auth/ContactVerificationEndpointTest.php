<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\UserStatus;
use App\Jobs\ProcessContactCodeRequest;
use App\Jobs\SendContactCode;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\InteractsWithContactCodes;
use Tests\TestCase;

class ContactVerificationEndpointTest extends TestCase
{
    use InteractsWithContactCodes;
    use RefreshDatabase;

    private const SEND_URL = '/api/v1/auth/contacts/verification/send';

    private const VERIFY_URL = '/api/v1/auth/contacts/verification/verify';

    public function test_send_answers_202_and_queues_a_code_for_an_unverified_contact(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        $this->postJson(self::SEND_URL, ['contact' => 'a@example.com'])
            ->assertAccepted()
            ->assertExactJson(['data' => ['message' => __('If the contact can be verified, a verification code has been sent.')]]);

        Queue::assertPushed(SendContactCode::class, 1);
    }

    public function test_send_does_not_look_the_contact_up_during_the_request(): void
    {
        Queue::fake([ProcessContactCodeRequest::class]);
        User::factory()->unverified()->create(['email' => 'a@example.com']);

        foreach (['a@example.com', 'ghost@example.com'] as $contact) {
            DB::enableQueryLog();
            $this->postJson(self::SEND_URL, ['contact' => $contact])->assertAccepted();
            $this->assertSame([], DB::getQueryLog(), 'The HTTP request must do the same work whatever the contact.');
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        Queue::assertPushed(ProcessContactCodeRequest::class, 2);
        $this->assertSame(0, ContactVerification::query()->count());
    }

    public function test_send_gives_the_same_answer_for_unknown_and_already_verified_contacts(): void
    {
        $this->captureContactCodes();
        User::factory()->unverified()->create(['email' => 'pending@example.com']);
        User::factory()->create(['email' => 'verified@example.com']);

        $answers = collect(['pending@example.com', 'verified@example.com', 'ghost@example.com', '+22249999999'])
            ->map(fn (string $contact): array => collect($this->postJson(self::SEND_URL, ['contact' => $contact])->assertAccepted()->json())->except('request_id')->all());

        $this->assertCount(1, $answers->unique());
        Queue::assertPushed(SendContactCode::class, 1);
    }

    public function test_send_rejects_a_malformed_contact(): void
    {
        $this->postJson(self::SEND_URL, ['contact' => 'nonsense'])->assertUnprocessable()->assertJsonValidationErrors(['contact']);
        $this->postJson(self::SEND_URL, [])->assertUnprocessable()->assertJsonValidationErrors(['contact']);
    }

    public function test_verify_marks_the_contact_verified_and_activates_the_account(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com', 'status' => UserStatus::PendingVerification]);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);

        $this->postJson(self::VERIFY_URL, ['contact' => 'A@example.com', 'code' => '123456'])
            ->assertOk()
            ->assertExactJson(['data' => ['verified' => true]]);

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_verify_answers_422_with_the_same_body_for_every_kind_of_failure(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'a@example.com']);
        ContactVerification::factory()->for($user)->create(['contact' => 'a@example.com']);
        ContactVerification::factory()->for($user)->expired()->create(['contact' => 'old@example.com']);

        $answers = collect([['a@example.com', '999999'], ['ghost@example.com', '123456'], ['old@example.com', '123456']])
            ->map(fn (array $input): array => collect(
                $this->postJson(self::VERIFY_URL, ['contact' => $input[0], 'code' => $input[1]])
                    ->assertUnprocessable()
                    ->assertJsonPath('code', 'invalid_verification_code')
                    ->json()
            )->except('request_id')->all());

        $this->assertCount(1, $answers->unique());
    }

    public function test_verify_rejects_a_malformed_code(): void
    {
        $this->postJson(self::VERIFY_URL, ['contact' => 'a@example.com', 'code' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
        $this->postJson(self::VERIFY_URL, [])->assertUnprocessable()->assertJsonValidationErrors(['contact', 'code']);
    }

    public function test_full_flow_register_then_verify_then_sign_in_with_full_access(): void
    {
        $this->captureContactCodes();

        $this->postJson('/api/v1/auth/register', ['name' => 'Moctar', 'phone' => '41 11 11 11', 'password' => self::PASSWORD])->assertAccepted();
        $this->postJson(self::VERIFY_URL, ['contact' => '+22241111111', 'code' => $this->lastCode()])->assertOk();

        $this->postJson('/api/v1/auth/login', ['identifier' => '41111111', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.abilities', ['access-api']);
    }
}
