<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\ResetPassword;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Sets a new password with the code received through {@see ForgotPassword}.
 *
 * Calls {@see ResetPassword} directly (no internal HTTP call), per ADR 0002.
 */
class SetNewPassword extends Component
{
    use AppliesNamedRateLimiter;

    public string $contact = '';

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->contact = (string) session('pending_password_reset_contact', '');
    }

    public function resetPassword(ResetPassword $resetPassword): void
    {
        try {
            $rules = ResetPassword::rules();
            $rules['password'][] = 'confirmed';

            $validated = Validator::make(
                [
                    'contact' => $this->contact,
                    'code' => $this->code,
                    'password' => $this->password,
                    'password_confirmation' => $this->password_confirmation,
                ],
                $rules,
            )->validate();

            // Same limits, same keys, as the API's `throttle:otp-verify` (see the trait).
            $this->applyNamedRateLimiter(
                'otp-verify',
                $this->authRateLimits()->otpVerify($validated['contact'], (string) request()->ip()),
                'code',
                'auth.password_reset.throttled_verify',
            );

            try {
                $resetPassword->handle($validated['contact'], $validated['code'], $validated['password']);
            } catch (InvalidVerificationCodeException) {
                $this->code = '';

                throw ValidationException::withMessages(['code' => __('auth.password_reset.invalid_code')]);
            }

            session()->forget('pending_password_reset_contact');
            session()->flash('status', __('auth.password_reset.success'));
            session()->flash('status_type', 'success');

            $this->redirect(route('login'));
        } finally {
            $this->password = '';
            $this->password_confirmation = '';
        }
    }

    public function render(): View
    {
        return view('livewire.auth.set-new-password')
            ->layout('components.layouts.app', [
                'title' => __('auth.password_reset.reset_title'),
                'description' => __('auth.password_reset.reset_description'),
            ]);
    }
}
