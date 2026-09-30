<?php

namespace App\Livewire\Account;

use App\Actions\Auth\ChangePassword;
use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use App\Models\User;
use App\Support\ContactMasker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Minimal "my account" page: name, status, masked contacts, notification preferences and a
 * change-password form. Guarded
 * by the `auth` and `account.active` web middleware (see routes/web.php): only a fully active,
 * signed-in account reaches this component.
 *
 * Calls {@see ChangePassword} directly (no internal HTTP call), per ADR 0002. Contacts are never
 * shown in clear: {@see ContactMasker} is the same masking logic the API resources use.
 */
class Show extends Component
{
    use AppliesNamedRateLimiter;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Shown after a successful password change; a session flash would not do (Livewire re-renders
     * only this component's own view on that request, never the surrounding layout).
     */
    public ?string $passwordUpdated = null;

    public bool $email_notifications = true;

    public string $locale = '';

    public ?string $preferencesSaved = null;

    public function mount(): void
    {
        $user = $this->currentUser();
        $this->email_notifications = $user->email_notifications;
        $this->locale = $user->locale;
    }

    /**
     * Same action and rules as `PATCH /api/v1/me/preferences`.
     */
    public function savePreferences(UpdateNotificationPreferences $update): void
    {
        $this->resetErrorBag();
        $this->preferencesSaved = null;

        /** @var array{email_notifications: bool, locale: string} $validated */
        $validated = Validator::make(
            ['email_notifications' => $this->email_notifications, 'locale' => $this->locale],
            UpdateNotificationPreferences::rules(),
        )->validate();

        $update->handle($this->currentUser(), $validated);

        $this->preferencesSaved = __('notifications.preferences.saved');
    }

    public function updatePassword(ChangePassword $changePassword): void
    {
        // Validator::make() does not clear Livewire's error bag the way $this->validate() does.
        $this->resetErrorBag();
        $this->passwordUpdated = null;

        try {
            $rules = ChangePassword::rules();
            $rules['password'][] = 'confirmed';

            $validated = Validator::make(
                [
                    'current_password' => $this->current_password,
                    'password' => $this->password,
                    'password_confirmation' => $this->password_confirmation,
                ],
                $rules,
            )->validate();

            // Same limits, same keys, as the API's `throttle:password-update` (see the trait).
            $this->applyNamedRateLimiter(
                'password-update',
                $this->authRateLimits()->passwordUpdate($this->currentUser()->getAuthIdentifier(), (string) request()->ip()),
                'current_password',
                'auth.account.password_throttled',
            );

            // Throws a ValidationException on `current_password` itself when it does not match;
            // Livewire catches it and populates this component's error bag like any other.
            $changePassword->handle($this->currentUser(), $validated['current_password'], $validated['password']);

            $this->passwordUpdated = __('auth.account.password_updated');
        } finally {
            $this->current_password = '';
            $this->password = '';
            $this->password_confirmation = '';
        }
    }

    public function render(): View
    {
        $user = $this->currentUser();

        return view('livewire.account.show', [
            'accountName' => $user->name,
            'accountStatus' => $user->status,
            'maskedEmail' => ContactMasker::email($user->email),
            'maskedPhone' => ContactMasker::phone($user->phone),
            'hasVerifiedEmail' => $user->email !== null && $user->email_verified_at !== null,
            'locales' => collect((array) config('app.supported_locales'))
                ->mapWithKeys(fn (string $code): array => [$code => __('nav.languages.'.$code)])
                ->all(),
        ])->layout('components.layouts.app', [
            'title' => __('auth.account.title'),
            'description' => __('auth.account.description'),
        ]);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
