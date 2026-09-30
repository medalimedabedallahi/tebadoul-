<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AuthenticateCredentials;
use App\Actions\Auth\LoginUser;
use App\Enums\UserStatus;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Web sign-in: opens a session (`Auth::login` + session regeneration), unlike the API which
 * issues a Sanctum token (see {@see LoginUser}). Both share the same credential
 * check, {@see AuthenticateCredentials}, so the rules for what counts as valid credentials live in
 * exactly one place.
 *
 * Security: a session is only ever opened for a fully {@see UserStatus::Active} account
 * (config/sanctum.php uses the `web` guard, so a session also authenticates the API).
 *
 * Anti-enumeration: an account pending verification gets EXACTLY the generic `auth.failed` error
 * of an unknown identifier or a wrong password (no redirection to the verification screen, no
 * session value). {@see AuthenticateCredentials} only matches verified contacts, and a pending
 * account has none. A distinct outcome for "pending + right password" would let anyone register a
 * contact with a password of their choice, then sign in, and learn whether the contact was free
 * (pending account created) or already taken. A person who has not verified yet finishes on the
 * verification screen, reached from the registration flow, where they can request a new code.
 */
class Login extends Component
{
    use AppliesNamedRateLimiter;

    public string $identifier = '';

    public string $password = '';

    public function login(AuthenticateCredentials $authenticate): void
    {
        try {
            $validated = Validator::make(
                ['identifier' => $this->identifier, 'password' => $this->password],
                AuthenticateCredentials::rules(),
            )->validate();

            // Same limits, same keys, as the API's `throttle:login` (see the trait).
            $this->applyNamedRateLimiter(
                'login',
                $this->authRateLimits()->login($validated['identifier'], (string) request()->ip()),
                'identifier',
                'auth.login.throttled',
            );

            try {
                $user = $authenticate->handle($validated['identifier'], $validated['password']);
            } catch (InvalidCredentialsException) {
                throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
            } catch (AccountSuspendedException) {
                throw ValidationException::withMessages(['identifier' => __('auth.login.suspended')]);
            }

            if (! $user->status->canSignIn()) {
                // Unreachable through AuthenticateCredentials (it never returns a pending account);
                // kept so that a session is never opened for an account that is not active.
                throw ValidationException::withMessages(['identifier' => __('auth.failed')]);
            }

            Auth::login($user);
            session()->regenerate();

            $this->redirect(session()->pull('url.intended', route('account.show')));
        } finally {
            // Never keep the plaintext password in the component's server-side state.
            $this->password = '';
        }
    }

    public function render(): View
    {
        return view('livewire.auth.login')
            ->layout('components.layouts.app', [
                'title' => __('auth.login.title'),
                'description' => __('auth.login.description'),
            ]);
    }
}
