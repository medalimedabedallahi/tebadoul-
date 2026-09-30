<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserSuspensionEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_suspends_an_ordinary_user_without_exposing_private_data(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create([
            'email' => 'private@example.com',
            'phone' => '+22241111111',
            'phone_verified_at' => now(),
        ]);

        $response = $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url($target), ['reason' => 'Repeated abusive messages.']);

        $response->assertOk()->assertExactJson([
            'data' => [
                'public_id' => $target->public_id,
                'status' => 'suspended',
            ],
        ]);
        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
        $this->assertSame('Repeated abusive messages.', AuditLog::query()->sole()->reason);
        $this->assertStringNotContainsString('private@example.com', (string) $response->getContent());
        $this->assertStringNotContainsString('+22241111111', (string) $response->getContent());
    }

    public function test_returns_401_without_authentication(): void
    {
        $target = User::factory()->create();

        $this->postJson($this->url($target), ['reason' => 'Repeated abuse.'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_unprivileged_roles_receive_the_same_403_for_existing_and_unknown_targets(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $target = User::factory()->create();
        $token = $this->tokenFor($actor);

        $existing = $this->withToken($token)->postJson($this->url($target), ['reason' => 'No access.']);
        $unknown = $this->withToken($token)->postJson($this->url((string) Str::ulid()), ['reason' => 'No access.']);

        $existing->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame($existing->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(
            collect($existing->json())->except('request_id')->all(),
            collect($unknown->json())->except('request_id')->all(),
        );
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_administrator_cannot_suspend_a_privileged_account(): void
    {
        $administrator = User::factory()->administrator()->create();
        $moderator = User::factory()->moderator()->create();

        $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url($moderator), ['reason' => 'Requires a future privileged workflow.'])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');

        $this->assertSame(UserStatus::Active, $moderator->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_administrator_receives_404_for_an_unknown_target(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url((string) Str::ulid()), ['reason' => 'Unknown target.'])
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    #[DataProvider('invalidReasons')]
    public function test_validates_the_required_bounded_reason(array $payload): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url($target), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_suspended_administrator_is_refused_before_the_action(): void
    {
        $administrator = User::factory()->administrator()->suspended()->create();
        $target = User::factory()->create();

        $this->withToken($this->tokenFor($administrator))
            ->postJson($this->url($target), ['reason' => 'No longer authorized.'])
            ->assertForbidden();

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_limited_legacy_token_is_refused(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $token = $administrator->createToken('legacy', [TokenAbility::VerifyContact->value])->plainTextToken;

        $this->withToken($token)
            ->postJson($this->url($target), ['reason' => 'Insufficient token ability.'])
            ->assertForbidden();

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_replays_an_idempotent_request_without_a_second_audit(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $headers = ['Idempotency-Key' => 'suspend-user-0001'];
        $token = $this->tokenFor($administrator);
        $payload = ['reason' => 'Repeated abusive messages.'];

        $first = $this->withToken($token)->postJson($this->url($target), $payload, $headers);
        $second = $this->withToken($token)->postJson($this->url($target), $payload, $headers);

        $first->assertOk();
        $second->assertOk()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertDatabaseCount('audit_logs', 1);
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function unprivilegedRoles(): array
    {
        return [
            'user' => [UserRole::User],
            'moderator' => [UserRole::Moderator],
        ];
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function invalidReasons(): array
    {
        return [
            'missing' => [[]],
            'blank' => [['reason' => '   ']],
            'too long' => [['reason' => str_repeat('a', 1001)]],
        ];
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }

    private function url(User|string $user): string
    {
        $publicId = $user instanceof User ? $user->public_id : $user;

        return "/api/v1/admin/users/$publicId/suspension";
    }
}
