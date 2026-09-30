<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;

/**
 * Changes the password of a signed-in account after checking the current one.
 *
 * Every other Sanctum token of the account is revoked; the token that made the request (the
 * current device) stays valid.
 *
 * Callers are expected to throttle (`throttle:password-update`, keyed by the account), because the
 * current password is checked.
 */
final class ChangePassword
{
    /**
     * Validation rules for the input, shared by the API and Livewire.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults(), 'max:255', 'different:current_password'],
        ];
    }

    /**
     * @throws ValidationException With an error on `current_password` when it does not match.
     */
    public function handle(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => __('The provided password does not match your current password.'),
            ]);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $user->forceFill([
                'password' => $newPassword,
                'remember_token' => Str::random(60),
            ])->save();

            /** @var PersonalAccessToken|TransientToken|null $current */
            $current = $user->currentAccessToken();
            $currentId = $current instanceof PersonalAccessToken ? $current->getKey() : null;

            $user->tokens()
                ->when($currentId !== null, fn ($query) => $query->whereKeyNot($currentId))
                ->delete();
        });
    }
}
