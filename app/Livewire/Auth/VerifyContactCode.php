<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\SendContactVerification;
use App\Actions\Auth\VerifyContact;
use App\Enums\ContactPurpose;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Verifies an email address or a phone number with the 6-digit code sent to it (registration, or
 * an account still pending verification at sign-in). Calls {@see VerifyContact} and
 * {@see SendContactVerification} (resend) directly, per ADR 0002.
 */
class VerifyContactCode extends Component
{
    use AppliesNamedRateLimiter;

    public string $contact = '';

    public string $code = '';

    /**
     * Shown after a successful resend; a session flash would not do (Livewire re-renders only
     * this component's own view on that request, never the surrounding layout).
     */
    public ?string $resendStatus = null;

    public function mount(): void
    {
        $this->contact = (string) session('pending_verification_contact', '');
    }

    public function verify(VerifyContact $verifyContact): void
    {
        $this->resendStatus = null;

        $validated = Validator::make(
            ['contact' => $this->contact, 'code' => $this->code],
            VerifyContact::rules(),
        )->validate();

        $this->applyNamedRateLimiter(
            'otp-verify',
            $this->authRateLimits()->otpVerify($validated['contact'], (string) request()->ip()),
            'code',
            'auth.verification.throttled_verify',
        );

        try {
            $verifyContact->handle($validated['contact'], $validated['code']);
        } catch (InvalidVerificationCodeException) {
            $this->code = '';

            throw ValidationException::withMessages(['code' => __('auth.verification.invalid_code')]);
        }

        session()->forget('pending_verification_contact');
        session()->flash('status', __('auth.verification.success'));
        session()->flash('status_type', 'success');

        $this->redirect(route('login'));
    }

    public function resend(SendContactVerification $sendVerification): void
    {
        $this->resendStatus = null;

        $validated = Validator::make(
            ['contact' => $this->contact],
            SendContactVerification::rules(),
        )->validate();

        $this->applyNamedRateLimiter(
            'otp-send',
            $this->authRateLimits()->otpSend($validated['contact'], (string) request()->ip()),
            'contact',
            'auth.verification.throttled_send',
        );

        $sendVerification->handle($validated['contact'], ContactPurpose::ContactVerification);

        $this->resendStatus = __('auth.verification.resend_success');
    }

    public function render(): View
    {
        return view('livewire.auth.verify-contact-code')
            ->layout('components.layouts.app', [
                'title' => __('auth.verification.title'),
                'description' => __('auth.verification.description'),
            ]);
    }
}
