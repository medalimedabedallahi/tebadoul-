<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_may_view_their_own_account(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('view', $user));
    }

    public function test_a_user_may_not_view_another_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->can('view', $other));
    }

    public function test_a_user_may_update_only_their_own_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue($user->can('update', $user));
        $this->assertFalse($user->can('update', $other));
    }

    public function test_an_ordinary_user_may_not_list_create_or_delete_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->can('viewAny', User::class));
        $this->assertFalse($user->can('create', User::class));
        $this->assertFalse($user->can('delete', $other));
        $this->assertFalse($user->can('delete', $user));
    }

    #[DataProvider('viewAnyPermissions')]
    public function test_only_administrators_may_list_accounts(UserRole $role, bool $allowed): void
    {
        $actor = User::factory()->create(['role' => $role]);

        $this->assertSame($allowed, $actor->can('viewAny', User::class));
    }

    public function test_an_administrator_still_may_not_view_an_unrestricted_user_resource(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->assertFalse($administrator->can('view', $target));
    }

    public function test_an_administrator_may_suspend_an_ordinary_user(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();

        $this->assertTrue($administrator->can('suspendAny', User::class));
        $this->assertTrue($administrator->can('suspend', $target));
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_other_roles_may_not_suspend_accounts(UserRole $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $target = User::factory()->create();

        $this->assertFalse($actor->can('suspendAny', User::class));
        $this->assertFalse($actor->can('suspend', $target));
    }

    #[DataProvider('privilegedRoles')]
    public function test_an_administrator_may_not_suspend_a_privileged_account(UserRole $role): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create(['role' => $role]);

        $this->assertFalse($administrator->can('suspend', $target));
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
     * @return array<string, array{UserRole}>
     */
    public static function privilegedRoles(): array
    {
        return [
            'moderator' => [UserRole::Moderator],
            'administrator' => [UserRole::Administrator],
        ];
    }

    /**
     * @return array<string, array{UserRole, bool}>
     */
    public static function viewAnyPermissions(): array
    {
        return [
            'user' => [UserRole::User, false],
            'moderator' => [UserRole::Moderator, false],
            'administrator' => [UserRole::Administrator, true],
        ];
    }
}
