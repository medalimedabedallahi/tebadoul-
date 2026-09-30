<?php

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Preferences of the account (PRD `PATCH /me/preferences`): whether notifications are also sent
 * by email, and the language of those emails. In-app notifications cannot be turned off.
 */
final class UpdateNotificationPreferences
{
    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'email_notifications' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', 'string', Rule::in((array) config('app.supported_locales'))],
        ];
    }

    /**
     * @param  array{email_notifications?: bool, locale?: string}  $preferences
     */
    public function handle(User $user, array $preferences): User
    {
        Gate::forUser($user)->authorize('update', $user);

        $user->forceFill(array_intersect_key($preferences, array_flip(['email_notifications', 'locale'])))->save();

        return $user;
    }
}
