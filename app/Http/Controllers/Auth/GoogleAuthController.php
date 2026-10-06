<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateGoogleUser;
use App\Actions\Auth\RegisterGoogleUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\GoogleIdentity;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        return $this->start($request, null);
    }

    public function link(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->start($request, $user->getKey());
    }

    private function start(Request $request, ?int $userId): RedirectResponse
    {
        $request->session()->forget(['google.pending', 'google.flow', 'state']);

        if (! GoogleIdentity::isConfigured()) {
            return $this->failure($userId === null ? 'login' : 'account.show', __('auth.google.unavailable'));
        }

        $request->session()->put('google.flow', ['user_id' => $userId, 'started_at' => now()->timestamp]);

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request, AuthenticateGoogleUser $authenticate): RedirectResponse
    {
        $flow = $request->session()->pull('google.flow');
        $request->session()->forget('google.pending');
        $destination = $request->user() !== null ? 'account.show' : 'login';

        if (! GoogleIdentity::isConfigured() || ! is_array($flow)
            || ! isset($flow['started_at']) || ! is_int($flow['started_at'])
            || $flow['started_at'] < now()->subMinutes(10)->timestamp
            || ! array_key_exists('user_id', $flow)
            || $flow['user_id'] !== $request->user()?->getAuthIdentifier()
            || $request->has('error')) {
            $request->session()->forget('state');

            return $this->failure($destination, __('auth.google.failed'));
        }

        try {
            $providerUser = Socialite::driver('google')->user();

            if (! $providerUser instanceof GoogleUser) {
                return $this->failure($destination, __('auth.google.failed'));
            }

            $identity = GoogleIdentity::fromProvider($providerUser);
            /** @var User|null $linkTo */
            $linkTo = $request->user();
            $user = $authenticate->handle($identity, $linkTo);
        } catch (InvalidStateException|GuzzleException) {
            $request->session()->forget('state');

            return $this->failure($destination, __('auth.google.failed'));
        } catch (ValidationException $exception) {
            return $this->failure($destination, $exception->errors()['google'][0] ?? __('auth.google.failed'));
        }

        if ($linkTo !== null) {
            $request->session()->regenerate();

            return redirect()->route('account.show')->with('status', __('auth.google.linked'))->with('status_type', 'success');
        }

        if ($user !== null) {
            return $this->signIn($request, $user);
        }

        $request->session()->regenerate();
        $request->session()->put('google.pending', [
            'subject' => $identity->subject,
            'email' => $identity->email,
            'name' => $identity->name,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        return redirect()->route('google.register');
    }

    public function registration(Request $request): View|RedirectResponse
    {
        $identity = $this->pendingIdentity($request);

        if ($identity === null || ! GoogleIdentity::isConfigured()) {
            $request->session()->forget('google.pending');

            return $this->failure('login', __('auth.google.expired'));
        }

        return view('auth.google-register', ['identity' => $identity]);
    }

    public function store(Request $request, RegisterGoogleUser $register): RedirectResponse
    {
        $identity = $this->pendingIdentity($request);

        if ($identity === null || ! GoogleIdentity::isConfigured()) {
            $request->session()->forget('google.pending');

            return $this->failure('login', __('auth.google.expired'));
        }

        $validated = $request->validate(RegisterGoogleUser::rules() + [
            'terms_version' => ['required', Rule::in([(string) config('legal.version')])],
        ]);
        $user = $register->handle($identity, $validated['name'], (bool) $validated['accept_terms'], app()->getLocale());
        $request->session()->forget('google.pending');

        return $this->signIn($request, $user);
    }

    private function pendingIdentity(Request $request): ?GoogleIdentity
    {
        $pending = $request->session()->get('google.pending');

        if (! is_array($pending) || ! isset($pending['subject'], $pending['email'], $pending['name'], $pending['expires_at'])
            || ! is_string($pending['subject']) || ! is_string($pending['email']) || ! is_string($pending['name'])
            || ! is_int($pending['expires_at']) || $pending['expires_at'] <= now()->timestamp) {
            return null;
        }

        return new GoogleIdentity($pending['subject'], $pending['email'], $pending['name']);
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('account.show'));
    }

    private function failure(string $route, string $message): RedirectResponse
    {
        return redirect()->route($route)->with('status', $message)->with('status_type', 'danger');
    }
}
