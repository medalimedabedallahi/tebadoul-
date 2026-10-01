<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RequestPasswordReset;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Requests a password reset code for an email address.
 *
 * Calls {@see RequestPasswordReset} directly (no internal HTTP call), per ADR 0002. The action
 * guarantees the anti-enumeration property (silent no-op for an unknown contact): this component
 * always shows the same generic outcome and sends the visitor to the reset screen.
 */
class ForgotPassword extends Component
{
    use AppliesNamedRateLimiter;

    public string $contact = '';

    public function request(RequestPasswordReset $requestPasswordReset): void
    {
        $validated = Validator::make(
            ['contact' => $this->contact],
            RequestPasswordReset::rules(),
        )->validate();

        // Same limits, same keys, as the API's `throttle:password-reset` (see the trait).
        $this->applyNamedRateLimiter(
            'password-reset',
            $this->authRateLimits()->passwordReset($validated['contact'], (string) request()->ip()),
            'contact',
            'auth.password_reset.throttled',
        );

        $requestPasswordReset->handle($validated['contact']);

        session(['pending_password_reset_contact' => $validated['contact']]);
        session()->flash('status', __('auth.password_reset.sent'));
        session()->flash('status_type', 'success');

        $this->redirect(route('password.reset'));
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password')
            ->layout('components.layouts.app', [
                'title' => __('auth.password_reset.forgot_title'),
                'description' => __('auth.password_reset.forgot_description'),
            ]);
    }
}
