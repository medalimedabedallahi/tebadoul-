<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterUser;
use App\Livewire\Concerns\AppliesNamedRateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Account creation with a required email and an optional phone number.
 *
 * Calls {@see RegisterUser} directly (no internal HTTP call), per ADR 0002. The action itself
 * guarantees the anti-enumeration property (silent no-op for a contact already taken): this
 * component always shows the same generic outcome and sends the visitor to contact verification.
 */
class Register extends Component
{
    use AppliesNamedRateLimiter;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $accept_terms = false;

    public function register(RegisterUser $registerUser): void
    {
        $rules = RegisterUser::rules();
        $rules['password'][] = 'confirmed';

        $validated = Validator::make(
            [
                'name' => $this->name,
                'email' => $this->email !== '' ? $this->email : null,
                'phone' => $this->phone !== '' ? $this->phone : null,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'accept_terms' => $this->accept_terms,
            ],
            $rules,
        )->validate();

        // Same limits, same keys, as the API's `throttle:register` (see the trait).
        $this->applyNamedRateLimiter(
            'register',
            $this->authRateLimits()->register($validated['email'], $validated['phone'], (string) request()->ip()),
            'email',
            'auth.verification.throttled_send',
        );

        $registerUser->handle(
            name: $validated['name'],
            email: $validated['email'],
            phone: $validated['phone'],
            password: $validated['password'],
            locale: app()->getLocale(),
        );

        session([
            'pending_verification_contact' => $validated['email'],
        ]);
        session()->flash('status', __('auth.register.success'));
        session()->flash('status_type', 'success');

        $this->redirect(route('verification.show'));
    }

    public function render(): View
    {
        return view('livewire.auth.register')
            ->layout('components.layouts.app', [
                'title' => __('auth.register.title'),
                'description' => __('auth.register.description'),
            ]);
    }
}
