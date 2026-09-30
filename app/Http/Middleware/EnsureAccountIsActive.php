<?php

namespace App\Http\Middleware;

use App\Actions\Auth\SuspendUser;
use App\Enums\ApiErrorCode;
use App\Enums\UserStatus;
use App\Http\Responses\ApiError;
use App\Livewire\Auth\Login;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a fully active account, not merely an authenticated one. Checked on EVERY request, from
 * the account as it is stored now, so a suspension ({@see SuspendUser}) takes effect immediately
 * even for a token or a session issued before it.
 *
 * - API (`api/*`): an account that is not {@see UserStatus::Active} gets the uniform 403
 *   `forbidden` error, with no hint of the reason (pending or suspended). This covers bearer
 *   tokens and first-party sessions alike (Sanctum authenticates both).
 * - Web: {@see Login} never opens a session for an account that is not active; this is defense
 *   in depth for a session that became stale afterwards. A suspended account is signed out.
 *
 * A guest passes through: put this middleware after `auth` / `auth:sanctum`.
 */
final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->status === UserStatus::Active) {
            return $next($request);
        }

        if ($request->is('api/*')) {
            return ApiError::make($request, 403, ApiErrorCode::Forbidden);
        }

        // A deleted account's other sessions (another browser, another device) end here too.
        if (in_array($user->status, [UserStatus::Suspended, UserStatus::Deleted], true)) {
            $deleted = $user->status === UserStatus::Deleted;

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route($deleted ? 'home' : 'login')
                ->with('status', __($deleted ? 'auth.account.deleted' : 'auth.login.suspended'))
                ->with('status_type', $deleted ? 'info' : 'danger');
        }

        return redirect()->route('verification.show')
            ->with('status', __('auth.login.pending_verification'))
            ->with('status_type', 'warning');
    }
}
