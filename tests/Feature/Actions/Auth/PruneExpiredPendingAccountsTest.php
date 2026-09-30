<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\PruneExpiredPendingAccounts;
use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredPendingAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_only_expired_unverified_pending_accounts_and_their_access_data(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        config([
            'auth.pending_accounts.ttl_days' => 7,
            'session.driver' => 'database',
        ]);
        $expired = $this->pendingUserCreatedAt('2026-09-15 11:59:59');
        $recent = $this->pendingUserCreatedAt('2026-09-17 12:00:00');
        $atCutoff = $this->pendingUserCreatedAt('2026-09-16 12:00:00');
        $active = User::factory()->unverified()->create([
            'status' => UserStatus::Active,
            'created_at' => '2026-09-01 12:00:00',
        ]);
        $verified = User::factory()->create([
            'status' => UserStatus::PendingVerification,
            'created_at' => '2026-09-01 12:00:00',
        ]);
        $expired->createToken('phone');
        $active->createToken('kept');
        $code = ContactVerification::factory()->for($expired)->create(['contact' => $expired->email]);
        DB::table('sessions')->insert([
            ['id' => 'expired-session', 'user_id' => $expired->id, 'payload' => 'payload', 'last_activity' => now()->timestamp],
            ['id' => 'active-session', 'user_id' => $active->id, 'payload' => 'payload', 'last_activity' => now()->timestamp],
        ]);

        $deleted = app(PruneExpiredPendingAccounts::class)->handle();

        $this->assertSame(1, $deleted);
        $this->assertModelMissing($expired);
        $this->assertModelExists($recent);
        $this->assertModelExists($atCutoff);
        $this->assertModelExists($active);
        $this->assertModelExists($verified);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $expired->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $active->id]);
        $this->assertDatabaseMissing('contact_verifications', ['id' => $code->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'expired-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'active-session']);
        $this->assertSame(0, app(PruneExpiredPendingAccounts::class)->handle());
    }

    public function test_deletes_expired_accounts_across_multiple_batches(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        config(['auth.pending_accounts.ttl_days' => 7]);
        User::factory()->count(501)->unverified()->create([
            'status' => UserStatus::PendingVerification,
            'created_at' => '2026-09-01 12:00:00',
        ]);

        $deleted = app(PruneExpiredPendingAccounts::class)->handle();

        $this->assertSame(501, $deleted);
        $this->assertSame(0, User::query()->count());
    }

    public function test_keeps_an_expired_pending_account_named_in_the_audit_trail(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        config(['auth.pending_accounts.ttl_days' => 7]);
        $audited = $this->pendingUserCreatedAt('2026-09-01 12:00:00');
        $other = $this->pendingUserCreatedAt('2026-09-01 12:00:00');
        AuditLog::query()->create([
            'actor_id' => User::factory()->administrator()->create()->id,
            'target_user_id' => $audited->id,
            'action' => AuditAction::UserReinstated,
            'reason' => 'Appeal accepted.',
            'before' => ['status' => 'suspended'],
            'after' => ['status' => 'pending_verification'],
        ]);

        $this->assertSame(1, app(PruneExpiredPendingAccounts::class)->handle());

        $this->assertModelExists($audited);
        $this->assertModelMissing($other);
    }

    private function pendingUserCreatedAt(string $createdAt): User
    {
        return User::factory()->unverified()->create([
            'status' => UserStatus::PendingVerification,
            'created_at' => $createdAt,
        ]);
    }
}
