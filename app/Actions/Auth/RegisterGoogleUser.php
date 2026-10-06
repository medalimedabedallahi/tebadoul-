<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\GoogleIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RegisterGoogleUser
{
    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'accept_terms' => ['accepted']];
    }

    public function handle(GoogleIdentity $identity, string $name, bool $acceptTerms, ?string $locale = null): User
    {
        Validator::make(['name' => $name, 'accept_terms' => $acceptTerms], self::rules())->validate();

        try {
            return DB::transaction(function () use ($identity, $name, $locale): User {
                if (User::query()->where('email', $identity->email)->orWhere('google_id', $identity->subject)->exists()) {
                    throw ValidationException::withMessages(['google' => __('auth.google.existing_account')]);
                }

                $user = new User([
                    'name' => $name,
                    'email' => $identity->email,
                    'password' => Str::random(64),
                    'locale' => in_array($locale, (array) config('app.supported_locales'), true) ? $locale : app()->getLocale(),
                ]);
                $user->forceFill([
                    'google_id' => $identity->subject,
                    'email_verified_at' => now(),
                    'status' => UserStatus::Active,
                    'terms_accepted_at' => now(),
                    'terms_version' => (string) config('legal.version'),
                ])->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['google' => __('auth.google.existing_account')]);
        }
    }
}
