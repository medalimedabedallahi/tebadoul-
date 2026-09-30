<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Enums\ContactType;
use App\Enums\UserStatus;
use App\Exceptions\Admin\AccountNotSuspendedException;
use App\Jobs\RecomputeMatches;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Lifts the suspension of an ordinary account ({@see SuspendUser}) and records the administrator's
 * decision in the audit trail, in one transaction.
 *
 * The account returns to {@see UserStatus::Active} when it has at least one verified contact,
 * otherwise to {@see UserStatus::PendingVerification}: a reinstatement never activates an account
 * that has not verified a contact. No access is restored: tokens and sessions revoked by the
 * suspension stay revoked, the person signs in again.
 *
 * An account that is not suspended is refused with {@see AccountNotSuspendedException}, so a
 * repeated decision is never audited twice. HTTP callers also use the idempotency middleware.
 */
final class ReinstateUser
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return SuspendUser::rules();
    }

    /**
     * @throws AccountNotSuspendedException
     */
    public function handle(User $actor, string $targetPublicId, string $reason, ?string $ipAddress = null): User
    {
        Gate::forUser($actor)->authorize('suspendAny', User::class);

        return DB::transaction(function () use ($actor, $targetPublicId, $reason, $ipAddress): User {
            $locked = User::query()
                ->where('public_id', Str::lower($targetPublicId))
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('reinstate', $locked);

            if ($locked->status !== UserStatus::Suspended) {
                throw new AccountNotSuspendedException;
            }

            $before = ['status' => $locked->status->value];

            $locked->status = $locked->hasVerified(ContactType::Email) || $locked->hasVerified(ContactType::Phone)
                ? UserStatus::Active
                : UserStatus::PendingVerification;
            $locked->save();

            RecomputeMatches::forUser($locked);

            AuditLog::query()->create([
                'actor_id' => $actor->getKey(),
                'target_user_id' => $locked->getKey(),
                'action' => AuditAction::UserReinstated,
                'reason' => $reason,
                'before' => $before,
                'after' => ['status' => $locked->status->value],
                'ip_address' => $ipAddress,
            ]);

            return $locked;
        });
    }
}
