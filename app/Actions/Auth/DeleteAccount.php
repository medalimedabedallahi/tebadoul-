<?php

namespace App\Actions\Auth;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Jobs\RecomputeMatches;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Deletes the signed-in account at its owner's request (PRD `DELETE /me`), after checking the
 * password.
 *
 * The row is anonymized rather than removed, because the audit trail, the reports and the other
 * participants' conversations reference it: the name is replaced, the contacts, their
 * verifications and the password are erased, and the status becomes {@see UserStatus::Deleted},
 * which is final. The personal data around it goes too: professional profile, mobility requests
 * (soft-deleted, so that the matches that referenced them stay explainable), one-time codes,
 * notifications, tokens and, with the `database` driver, sessions. The engine then invalidates the
 * account's live matches and ends any agreement, as for a suspension (nothing to invalidate while
 * matching is disabled: no match exists). Messages already sent stay in the other participant's
 * conversation, as the privacy policy states.
 *
 * The contacts become free: the owner can register again with them. A later call is refused (the
 * account is no longer active); two concurrent calls delete once, the second finding the locked
 * row already deleted. Callers are expected to throttle (`throttle:password-update`), because the
 * password is checked.
 */
final class DeleteAccount
{
    public const ANONYMIZED_NAME = 'Compte supprimé';

    private const AUDIT_REASON = 'Suppression demandée par le titulaire du compte.';

    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @throws ValidationException With an error on `password` when it does not match.
     */
    public function handle(User $user, string $password, ?string $ipAddress = null): void
    {
        Gate::forUser($user)->authorize('deleteOwnAccount', $user);

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('The provided password does not match your current password.'),
            ]);
        }

        DB::transaction(function () use ($user, $ipAddress): void {
            $account = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($account->status === UserStatus::Deleted) {
                return;
            }

            $before = ['status' => $account->status->value];

            // Collects the requests to recompute now; the jobs run after commit, once they are gone.
            RecomputeMatches::forUser($account);
            $account->mobilityRequests()->get()->each->delete();
            $account->professionalProfile()->delete();
            $account->contactVerifications()->delete();
            $account->notifications()->delete();
            $account->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table((string) config('session.table', 'sessions'))
                    ->where('user_id', $account->getKey())
                    ->delete();
            }

            $account->forceFill([
                'name' => self::ANONYMIZED_NAME,
                'email' => null,
                'phone' => null,
                'google_id' => null,
                'email_verified_at' => null,
                'phone_verified_at' => null,
                'password' => Str::random(64),
                'remember_token' => Str::random(60),
                'email_notifications' => false,
                'status' => UserStatus::Deleted,
                'anonymized_at' => now(),
            ])->save();

            AuditLog::query()->create([
                'actor_id' => $account->getKey(),
                'target_user_id' => $account->getKey(),
                'action' => AuditAction::AccountDeleted,
                'reason' => self::AUDIT_REASON,
                'before' => $before,
                'after' => ['status' => UserStatus::Deleted->value],
                'ip_address' => $ipAddress,
            ]);
        });
    }
}
