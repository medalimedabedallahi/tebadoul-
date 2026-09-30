<?php

namespace App\Livewire\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Sign-out control embedded in the layout's navigation for a signed-in visitor.
 *
 * A Livewire action is always invoked over POST (the component's update request), so this never
 * behaves like an unsafe GET logout link.
 */
class LogoutButton extends Component
{
    public function logout(): RedirectResponse
    {
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('home');
    }

    public function render(): View
    {
        return view('livewire.auth.logout-button');
    }
}
