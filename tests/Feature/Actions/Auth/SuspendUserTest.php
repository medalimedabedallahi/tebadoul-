<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\SuspendUser;
use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\ContactVerification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SuspendUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspends_the_account_and_revokes_every_access_channel(): void
    {
        $this->travelTo('2026-09-23 12:00:00');
        config(['session.driver' => 'database']);
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->create(['remember_token' => 'original-token']);
        $other = User::factory()->create();
        $user->createToken('phone');
        $other->createToken('other');
        $activeCode = ContactVerification::factory()->for($user)->create(['contact' => $user->email]);
        $consumedCode = ContactVerification::factory()->for($user)->consumed()->create([
            'contact' => 'old@example.com',
            'consumed_at' => now()->subDay(),
        ]);
        DB::table('sessions')->insert([
            ['id' => 'user-session', 'user_id' => $user->id, 'payload' => 'payload', 'last_activity' => now()->timestamp],
            ['id' => 'other-session', 'user_id' => $other->id, 'payload' => 'payload', 'last_activity' => now()->timestamp],
        ]);

        $suspended = app(SuspendUser::class)->handle(
            $administrator,
            $user->public_id,
            'Repeated abusive messages.',
            '127.0.0.1',
        );

        $this->assertTrue($suspended->is($user));
        $this->assertSame(UserStatus::Suspended, $suspended->status);
        $this->assertNotSame('original-token', $suspended->getRememberToken());
        $this->assertSame(60, strlen((string) $suspended->getRememberToken()));
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $other->id]);
        $this->assertSame('2026-09-23 12:00:00', $activeCode->refresh()->consumed_at?->toDateTimeString());
        $this->assertSame('2026-09-22 12:00:00', $consumedCode->refresh()->consumed_at?->toDateTimeString());
        $this->assertDatabaseMissing('sessions', ['id' => 'user-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
        $audit = AuditLog::query()->sole();
        $this->assertSame(AuditAction::UserSuspended, $audit->action);
        $this->assertTrue($audit->actor->is($administrator));
        $this->assertTrue($audit->targetUser->is($user));
        $this->assertSame('Repeated abusive messages.', $audit->reason);
        $this->assertSame(['status' => 'active'], $audit->before);
        $this->assertSame(['status' => 'suspended'], $audit->after);
        $this->assertSame('127.0.0.1', $audit->ip_address);
    }

    public function test_revokes_access_created_for_an_already_suspended_account(): void
    {
        $administrator = User::factory()->administrator()->create();
        $user = User::factory()->suspended()->create();
        $user->createToken('unexpected-token');
        $code = ContactVerification::factory()->for($user)->create(['contact' => $user->email]);

        app(SuspendUser::class)->handle($administrator, $user->public_id, 'Further access detected.');

        $this->assertSame(UserStatus::Suspended, $user->status);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertNotNull($code->refresh()->consumed_at);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_an_unauthorized_actor_cannot_mutate_or_discover_the_target(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $target->createToken('phone');

        foreach ([$target->public_id, '01K5UNKNOWN0000000000000000'] as $publicId) {
            try {
                app(SuspendUser::class)->handle($actor, $publicId, 'Not authorized.');
                $this->fail('An ordinary user must not suspend an account.');
            } catch (AuthorizationException) {
                // Both targets are deliberately indistinguishable to an unauthorized actor.
            }
        }

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $target->id]);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rolls_back_the_suspension_when_the_audit_cannot_be_written(): void
    {
        $administrator = User::factory()->administrator()->create();
        $target = User::factory()->create();
        $target->createToken('phone');
        $code = ContactVerification::factory()->for($target)->create(['contact' => $target->email]);
        AuditLog::creating(static fn (): never => throw new RuntimeException('Audit unavailable.'));

        try {
            app(SuspendUser::class)->handle($administrator, $target->public_id, 'Must remain atomic.');
            $this->fail('The action must fail when its audit cannot be written.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $target->id]);
        $this->assertNull($code->fresh()->consumed_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
