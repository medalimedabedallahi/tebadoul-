<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\TokenAbility;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserIndexEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/admin/users';

    public function test_an_administrator_receives_a_paginated_privacy_safe_list(): void
    {
        $administrator = User::factory()->administrator()->create();
        $older = User::factory()->create([
            'name' => 'Private Name',
            'email' => 'private@example.com',
            'created_at' => '2026-09-20 12:00:00',
        ]);
        $newer = User::factory()->suspended()->create(['created_at' => '2026-09-21 12:00:00']);

        $response = $this->withToken($this->tokenFor($administrator))
            ->getJson(self::URL.'?per_page=20');

        $response->assertOk()
            ->assertJsonPath('data.0.public_id', $newer->public_id)
            ->assertJsonPath('data.0.status', 'suspended')
            ->assertJsonPath('data.1.public_id', $older->public_id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 20);
        $this->assertSame(['public_id', 'status'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString('Private Name', (string) $response->getContent());
        $this->assertStringNotContainsString('private@example.com', (string) $response->getContent());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_exact_filter_hides_privileged_and_unknown_accounts_as_empty_results(): void
    {
        $administrator = User::factory()->administrator()->create();
        $ordinary = User::factory()->create();
        $moderator = User::factory()->moderator()->create();
        $token = $this->tokenFor($administrator);

        $this->withToken($token)->getJson(self::URL.'?public_id='.$ordinary->public_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $ordinary->public_id);
        $this->withToken($token)->getJson(self::URL.'?public_id='.$moderator->public_id)
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withToken($token)->getJson(self::URL.'?public_id='.Str::ulid())
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unexpected_filters_cannot_expose_privileged_or_private_data(): void
    {
        $administrator = User::factory()->administrator()->create(['email' => 'admin@example.com']);
        $ordinary = User::factory()->create(['email' => 'ordinary@example.com']);
        User::factory()->moderator()->create(['email' => 'moderator@example.com']);

        $response = $this->withToken($this->tokenFor($administrator))->getJson(
            self::URL.'?role=administrator&email=admin%40example.com&phone=%2B22241111111&sort=id'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.public_id', $ordinary->public_id);
        $this->assertSame(['public_id', 'status'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString('admin@example.com', (string) $response->getContent());
        $this->assertStringNotContainsString('ordinary@example.com', (string) $response->getContent());
    }

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson(self::URL)
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_unprivileged_roles_receive_the_same_403_for_existing_and_unknown_filters(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $target = User::factory()->create();
        $token = $this->tokenFor($actor);

        $existing = $this->withToken($token)->getJson(self::URL.'?public_id='.$target->public_id);
        $unknown = $this->withToken($token)->getJson(self::URL.'?public_id='.Str::ulid());

        $existing->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->assertSame($existing->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame(
            collect($existing->json())->except('request_id')->all(),
            collect($unknown->json())->except('request_id')->all(),
        );
    }

    #[DataProvider('invalidQueries')]
    public function test_validates_supported_query_parameters(array $query): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->withToken($this->tokenFor($administrator))
            ->getJson(self::URL.'?'.http_build_query($query))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed');
    }

    public function test_a_suspended_administrator_is_refused(): void
    {
        $administrator = User::factory()->administrator()->suspended()->create();

        $this->withToken($this->tokenFor($administrator))
            ->getJson(self::URL)
            ->assertForbidden();
    }

    public function test_a_limited_legacy_token_is_refused(): void
    {
        $administrator = User::factory()->administrator()->create();
        $token = $administrator->createToken('legacy', [TokenAbility::VerifyContact->value])->plainTextToken;

        $this->withToken($token)->getJson(self::URL)->assertForbidden();
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
     * @return array<string, array{array<string, int|string>}>
     */
    public static function invalidQueries(): array
    {
        return [
            'invalid public id' => [['public_id' => 'not-a-ulid']],
            'page below one' => [['page' => 0]],
            'per page below one' => [['per_page' => 0]],
            'per page above one hundred' => [['per_page' => 101]],
        ];
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test', [TokenAbility::AccessApi->value])->plainTextToken;
    }
}
