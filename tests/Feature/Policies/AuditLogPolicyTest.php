<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{UserRole, UserStatus, bool}>
     */
    public static function accounts(): array
    {
        return [
            'active administrator' => [UserRole::Administrator, UserStatus::Active, true],
            'suspended administrator' => [UserRole::Administrator, UserStatus::Suspended, false],
            'moderator' => [UserRole::Moderator, UserStatus::Active, false],
            'user' => [UserRole::User, UserStatus::Active, false],
        ];
    }

    #[DataProvider('accounts')]
    public function test_only_an_active_administrator_reads_the_audit_trail_and_statistics(UserRole $role, UserStatus $status, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role, 'status' => $status]);

        $this->assertSame($allowed, $user->can('viewAny', AuditLog::class));
        $this->assertSame($allowed, $user->can('viewStatistics', User::class));
    }

    public function test_nobody_creates_changes_or_deletes_an_audit_entry(): void
    {
        $administrator = User::factory()->administrator()->create();
        $entry = AuditLog::query()->create([
            'actor_id' => $administrator->id,
            'target_user_id' => User::factory()->create()->id,
            'action' => 'user_suspended',
            'reason' => 'Test',
            'before' => [],
            'after' => [],
        ]);

        $this->assertFalse($administrator->can('create', AuditLog::class));
        $this->assertFalse($administrator->can('update', $entry));
        $this->assertFalse($administrator->can('delete', $entry));
    }
}
