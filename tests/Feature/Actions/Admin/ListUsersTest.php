<?php

namespace Tests\Feature\Actions\Admin;

use App\Actions\Admin\ListUsers;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_only_ordinary_accounts_in_a_stable_newest_first_order(): void
    {
        $administrator = User::factory()->administrator()->create();
        $oldest = User::factory()->create(['created_at' => '2026-09-20 12:00:00']);
        $pending = User::factory()->unverified()->create([
            'status' => UserStatus::PendingVerification,
            'created_at' => '2026-09-21 12:00:00',
        ]);
        $newest = User::factory()->suspended()->create(['created_at' => '2026-09-21 12:00:00']);
        User::factory()->moderator()->create(['created_at' => '2026-09-22 12:00:00']);

        $users = app(ListUsers::class)->handle($administrator);

        $this->assertSame([$newest->id, $pending->id, $oldest->id], $users->getCollection()->modelKeys());
        $this->assertSame(3, $users->total());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_filters_by_an_exact_public_id_without_revealing_privileged_accounts(): void
    {
        $administrator = User::factory()->administrator()->create();
        $ordinary = User::factory()->create();
        $moderator = User::factory()->moderator()->create();

        $found = app(ListUsers::class)->handle($administrator, $ordinary->public_id);
        $privileged = app(ListUsers::class)->handle($administrator, $moderator->public_id);
        $unknown = app(ListUsers::class)->handle($administrator, (string) Str::ulid());

        $this->assertSame([$ordinary->id], $found->getCollection()->modelKeys());
        $this->assertSame(0, $privileged->total());
        $this->assertSame(0, $unknown->total());
    }

    public function test_the_public_id_filter_ignores_case_like_ulids_do(): void
    {
        $administrator = User::factory()->administrator()->create();
        $ordinary = User::factory()->create();

        $found = app(ListUsers::class)->handle($administrator, Str::upper($ordinary->public_id));

        $this->assertSame([$ordinary->id], $found->getCollection()->modelKeys());
    }

    public function test_paginates_with_the_requested_page_size(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->count(3)->create();

        $users = app(ListUsers::class)->handle($administrator, page: 2, perPage: 2);

        $this->assertSame(3, $users->total());
        $this->assertSame(2, $users->currentPage());
        $this->assertSame(2, $users->perPage());
        $this->assertCount(1, $users->items());
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_refuses_unprivileged_roles_before_querying_accounts(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        User::factory()->create();

        try {
            app(ListUsers::class)->handle($actor);
            $this->fail('An unprivileged account must not list users.');
        } catch (AuthorizationException) {
            // Expected.
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('audit_logs', 0);
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
}
