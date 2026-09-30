<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserReinstatementEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_reinstates_a_suspended_user_without_exposing_private_data(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->suspended()->create(['email' => 'private@example.com']);

        $response = $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url($target), ['reason' => 'Appeal accepted.']);

        $response->assertOk()->assertExactJson([
            'data' => [
                'public_id' => $target->public_id,
                'status' => 'active',
            ],
        ]);
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertStringNotContainsString('private@example.com', (string) $response->getContent());
    }

    public function test_returns_409_for_an_account_that_is_not_suspended(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url($target), ['reason' => 'Nothing to lift.'])
            ->assertConflict()
            ->assertJsonPath('code', 'account_not_suspended');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_unprivileged_actors_receive_the_same_403_for_existing_and_unknown_targets(): void
    {
        $actor = User::factory()->moderator()->create();
        $target = User::factory()->suspended()->create();
        $token = $this->tokenFor($actor);

        $existing = $this->withToken($token)->postJson($this->url($target), ['reason' => 'No access.']);
        $unknown = $this->withToken($token)->postJson($this->url((string) Str::ulid()), ['reason' => 'No access.']);

        $existing->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame(
            collect($existing->json())->except('request_id')->all(),
            collect($unknown->json())->except('request_id')->all(),
        );
        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
    }

    public function test_returns_401_without_authentication(): void
    {
        $target = User::factory()->suspended()->create();

        $this->postJson($this->url($target), ['reason' => 'Appeal accepted.'])
            ->assertUnauthorized();

        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
    }

    public function test_requires_a_reason(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->suspended()->create();

        $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url($target), ['reason' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
    }

    public function test_replays_an_idempotent_request_without_a_conflict_or_second_audit(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->suspended()->create();
        $headers = ['Idempotency-Key' => 'reinstate-user-0001'];
        $token = $this->tokenFor($administrator);
        $payload = ['reason' => 'Appeal accepted.'];

        $first = $this->withToken($token)->postJson($this->url($target), $payload, $headers);
        $second = $this->withToken($token)->postJson($this->url($target), $payload, $headers);

        $first->assertOk();
        $second->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }

    private function url(User|string $user): string
    {
        $publicId = $user instanceof User ? $user->public_id : $user;

        return "/api/v1/admin/users/$publicId/reinstatement";
    }
}
