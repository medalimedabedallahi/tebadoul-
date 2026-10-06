<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Auth\GoogleIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class AuthenticateGoogleUser
{
    /**
     * An email match alone never links accounts. Linking requires an active signed-in owner
     * and the same verified address; sign-in thereafter uses Google's stable subject.
     */
    public function handle(GoogleIdentity $identity, ?User $linkTo = null): ?User
    {
        try {
            return DB::transaction(function () use ($identity, $linkTo): ?User {
                if ($linkTo !== null) {
                    $account = User::query()->whereKey($linkTo->getKey())->lockForUpdate()->firstOrFail();
                    Gate::forUser($account)->authorize('linkGoogle', $account);

                    if ($account->email !== $identity->email || $account->email_verified_at === null) {
                        throw ValidationException::withMessages(['google' => __('auth.google.email_mismatch')]);
                    }

                    if (($account->google_id !== null && $account->google_id !== $identity->subject)
                        || User::query()->where('google_id', $identity->subject)->whereKeyNot($account->getKey())->exists()) {
                        throw ValidationException::withMessages(['google' => __('auth.google.already_linked')]);
                    }

                    $account->forceFill(['google_id' => $identity->subject])->save();

                    return $account;
                }

                $account = User::query()->where('google_id', $identity->subject)->lockForUpdate()->first();

                if ($account !== null) {
                    if (! $account->status->canSignIn() || $account->email_verified_at === null || $account->email !== $identity->email) {
                        throw ValidationException::withMessages(['google' => __('auth.google.sign_in_refused')]);
                    }

                    return $account;
                }

                if (User::query()->where('email', $identity->email)->exists()) {
                    throw ValidationException::withMessages(['google' => __('auth.google.existing_account')]);
                }

                return null;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['google' => __('auth.google.already_linked')]);
        }
    }
}
