<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\ReinstateUser;
use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Exceptions\Admin\AccountNotSuspendedException;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ReinstateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_reinstates_a_suspended_account_with_a_verified_contact_and_audits_it(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->suspended()->create();

        $reinstated = app(ReinstateUser::class)->handle(
            $administrator,
            $user->public_id,
            'Appeal accepted.',
            '127.0.0.1',
        );

        $this->assertTrue($reinstated->is($user));
        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $audit = AuditLog::query()->sole();
        $this->assertSame(AuditAction::UserReinstated, $audit->action);
        $this->assertTrue($audit->actor->is($administrator));
        $this->assertTrue($audit->targetUser->is($user));
        $this->assertSame('Appeal accepted.', $audit->reason);
        $this->assertSame(['status' => 'suspended'], $audit->before);
        $this->assertSame(['status' => 'active'], $audit->after);
        $this->assertSame('127.0.0.1', $audit->ip_address);
    }

    public function test_accepts_the_public_id_in_upper_case(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->suspended()->create();

        app(ReinstateUser::class)->handle($administrator, strtoupper($user->public_id), 'Appeal accepted.');

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
    }

    public function test_an_account_without_verified_contact_returns_to_pending_verification(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->unverified()->suspended()->create();

        app(ReinstateUser::class)->handle($administrator, $user->public_id, 'Appeal accepted.');

        $this->assertSame(UserStatus::PendingVerification, $user->fresh()->status);
        $this->assertSame(['status' => 'pending_verification'], AuditLog::query()->sole()->after);
    }

    public function test_refuses_an_account_that_is_not_suspended_without_auditing(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create();

        try {
            app(ReinstateUser::class)->handle($administrator, $user->public_id, 'Nothing to lift.');
            $this->fail('Only a suspended account can be reinstated.');
        } catch (AccountNotSuspendedException) {
            // Expected.
        }

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_unauthorized_actor_cannot_mutate_or_discover_the_target(): void
    {
        $actor = User::factory()->moderator()->create();
        $target = User::factory()->suspended()->create();

        foreach ([$target->public_id, '01K5UNKNOWN0000000000000000'] as $publicId) {
            try {
                app(ReinstateUser::class)->handle($actor, $publicId, 'Not authorized.');
                $this->fail('Only an administrator may reinstate an account.');
            } catch (AuthorizationException) {
                // Both targets are deliberately indistinguishable to an unauthorized actor.
            }
        }

        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_privileged_account_cannot_be_targeted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $moderator = User::factory()->moderator()->suspended()->create();

        $this->expectException(AuthorizationException::class);

        try {
            app(ReinstateUser::class)->handle($administrator, $moderator->public_id, 'Privileged.');
        } finally {
            $this->assertSame(UserStatus::Suspended, $moderator->fresh()->status);
        }
    }

    public function test_rolls_back_the_reinstatement_when_the_audit_cannot_be_written(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->suspended()->create();
        AuditLog::creating(static fn (): never => throw new RuntimeException('Audit unavailable.'));

        try {
            app(ReinstateUser::class)->handle($administrator, $target->public_id, 'Must remain atomic.');
            $this->fail('The action must fail when its audit cannot be written.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertSame(UserStatus::Suspended, $target->fresh()->status);
    }
}
