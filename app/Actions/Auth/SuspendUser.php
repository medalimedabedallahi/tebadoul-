<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Jobs\RecomputeMatches;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Suspends an ordinary account, cuts every access it currently has and records the administrator's
 * decision in the audit trail.
 *
 * In one transaction: the status becomes {@see UserStatus::Suspended}, every Sanctum token is
 * revoked, the "remember me" token is rotated, and the unused one-time codes of the account are
 * invalidated. Sessions: with the `database` session driver, the account's session rows are
 * deleted. Other drivers (Redis, file, cookie) cannot list the sessions of one user; those are
 * refused on their next request by {@see EnsureAccountIsActive}, which re-reads the status every
 * time (403 on the API, sign-out on the web).
 *
 * Repeating the action revokes whatever access was created since. HTTP callers use the
 * idempotency middleware to avoid recording the same administrative decision twice on retries.
 */
final class SuspendUser
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function handle(User $actor, string $targetPublicId, string $reason, ?string $ipAddress = null): User
    {
        Gate::forUser($actor)->authorize('suspendAny', User::class);

        return DB::transaction(function () use ($actor, $targetPublicId, $reason, $ipAddress): User {
            $locked = User::query()
                ->where('public_id', Str::lower($targetPublicId))
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('suspend', $locked);
            $before = ['status' => $locked->status->value];

            $locked->status = UserStatus::Suspended;
            $locked->setRememberToken(Str::random(60));
            $locked->save();

            $locked->tokens()->delete();
            $locked->contactVerifications()->unconsumed()->update(['consumed_at' => now()]);

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table((string) config('session.table', 'sessions'))
                    ->where('user_id', $locked->getKey())
                    ->delete();
            }

            RecomputeMatches::forUser($locked);

            AuditLog::query()->create([
                'actor_id' => $actor->getKey(),
                'target_user_id' => $locked->getKey(),
                'action' => AuditAction::UserSuspended,
                'reason' => $reason,
                'before' => $before,
                'after' => ['status' => $locked->status->value],
                'ip_address' => $ipAddress,
            ]);

            return $locked;
        });
    }
}
