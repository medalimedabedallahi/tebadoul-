<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes the accounts still {@see UserStatus::PendingVerification}, without any verified
 * contact, created more than `auth.pending_accounts.ttl_days` days ago: their contacts are released
 * for a new registration and no unverified personal data is kept indefinitely.
 *
 * Idempotent and safe to run concurrently with verifications: each batch re-checks the condition
 * on locked rows, so an account verified in the meantime is never deleted. Its tokens, codes and
 * database sessions are deleted with it. Scheduled daily (routes/console.php,
 * `auth:prune-pending-accounts`).
 */
final class PruneExpiredPendingAccounts
{
    private const BATCH_SIZE = 500;

    /**
     * @return int Number of accounts deleted.
     */
    public function handle(): int
    {
        $before = now()->subDays(max(1, (int) config('auth.pending_accounts.ttl_days', 7)));
        $deleted = 0;

        User::query()
            ->expiredPendingVerification($before)
            ->select('id')
            ->chunkById(self::BATCH_SIZE, function ($users) use ($before, &$deleted): void {
                $deleted += DB::transaction(function () use ($users, $before): int {
                    $ids = User::query()
                        ->whereKey($users->modelKeys())
                        ->expiredPendingVerification($before)
                        ->lockForUpdate()
                        ->pluck('id')
                        ->all();

                    if ($ids === []) {
                        return 0;
                    }

                    DB::table('personal_access_tokens')
                        ->where('tokenable_type', (new User)->getMorphClass())
                        ->whereIn('tokenable_id', $ids)
                        ->delete();

                    DB::table('contact_verifications')->whereIn('user_id', $ids)->delete();

                    if (config('session.driver') === 'database') {
                        DB::connection(config('session.connection'))
                            ->table((string) config('session.table', 'sessions'))
                            ->whereIn('user_id', $ids)
                            ->delete();
                    }

                    return User::query()->whereKey($ids)->delete();
                });
            });

        return $deleted;
    }
}
