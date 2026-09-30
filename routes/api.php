<?php

use App\Enums\TokenAbility;
use App\Http\Controllers\Api\V1\Admin\AuditLogIndexController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Admin\StatisticsController;
use App\Http\Controllers\Api\V1\Admin\UserIndexController;
use App\Http\Controllers\Api\V1\Admin\UserReinstatementController;
use App\Http\Controllers\Api\V1\Admin\UserSuspensionController;
use App\Http\Controllers\Api\V1\Auth\ContactVerificationController;
use App\Http\Controllers\Api\V1\Auth\DeleteAccountController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MatchController;
use App\Http\Controllers\Api\V1\MatchMessageController;
use App\Http\Controllers\Api\V1\MatchParticipationController;
use App\Http\Controllers\Api\V1\MatchTrustController;
use App\Http\Controllers\Api\V1\MobilityRequestController;
use App\Http\Controllers\Api\V1\MobilityRequestStatusController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProfessionalProfileController;
use App\Http\Controllers\Api\V1\ReferenceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function (): void {
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Public, read-only reference data: no personal data, only active entries.
    Route::prefix('references')->name('api.v1.references.')->controller(ReferenceController::class)->group(function (): void {
        Route::get('/sectors', 'sectors')->name('sectors');
        Route::get('/professions', 'professions')->name('professions');
        Route::get('/specialties', 'specialties')->name('specialties');
        Route::get('/grades', 'grades')->name('grades');
        Route::get('/wilayas', 'wilayas')->name('wilayas');
        Route::get('/wilayas/{wilayaCode}/moughataas', 'moughataas')->name('wilayas.moughataas');
        Route::get('/establishments', 'establishments')->name('establishments');
    });

    Route::prefix('auth')->name('api.v1.auth.')->group(function (): void {
        // Public. Each throttle reads ONE explicit field of the request (see AuthRateLimits);
        // anti-enumeration answers are uniform.
        Route::post('/register', RegisterController::class)
            ->middleware(['throttle:register', 'idempotent'])
            ->name('register');
        Route::post('/login', LoginController::class)
            ->middleware('throttle:login')
            ->name('login');
        Route::post('/contacts/verification/send', [ContactVerificationController::class, 'send'])
            ->middleware('throttle:otp-send')
            ->name('contacts.verification.send');
        Route::post('/contacts/verification/verify', [ContactVerificationController::class, 'verify'])
            ->middleware('throttle:otp-verify')
            ->name('contacts.verification.verify');
        Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:password-reset')
            ->name('password.forgot');
        Route::post('/password/reset', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:otp-verify')
            ->name('password.reset');

        // Authenticated routes: `account.active` re-checks the status of the account on EVERY
        // request (a suspended account, or a session of an account that is not active, gets 403),
        // whatever the token abilities or the session say.
        Route::middleware(['auth:sanctum', 'account.active', 'ability:'.TokenAbility::AccessApi->value.','.TokenAbility::VerifyContact->value])
            ->group(function (): void {
                Route::get('/me', MeController::class)->name('me');
                Route::post('/logout', LogoutController::class)->name('logout');
            });

        Route::middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value])
            ->group(function (): void {
                Route::put('/password', [PasswordController::class, 'update'])
                    ->middleware('throttle:password-update')
                    ->name('password.update');
            });
    });

    Route::prefix('me')->name('api.v1.me.')
        ->middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value])
        ->controller(ProfessionalProfileController::class)
        ->group(function (): void {
            Route::get('/profile', 'show')->name('profile.show');
            Route::put('/profile', 'update')->name('profile.update');
        });

    Route::patch('/me/preferences', [NotificationController::class, 'updatePreferences'])
        ->middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value])
        ->name('api.v1.me.preferences.update');

    // Checks the password: same throttle as a password change (keyed by the account).
    Route::delete('/me', DeleteAccountController::class)
        ->middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value, 'throttle:password-update'])
        ->name('api.v1.me.destroy');

    Route::prefix('notifications')->name('api.v1.notifications.')
        ->middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value])
        ->controller(NotificationController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/read-all', 'readAll')->name('read-all');
            Route::post('/{notification}/read', 'read')->name('read');
        });

    Route::prefix('mobility-requests')->name('api.v1.mobility-requests.')
        ->middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value])
        ->group(function (): void {
            Route::get('/', [MobilityRequestController::class, 'index'])->name('index');
            Route::post('/', [MobilityRequestController::class, 'store'])->middleware('idempotent')->name('store');
            Route::get('/{mobilityRequest}', [MobilityRequestController::class, 'show'])->name('show');
            Route::patch('/{mobilityRequest}', [MobilityRequestController::class, 'update'])->name('update');
            Route::delete('/{mobilityRequest}', [MobilityRequestController::class, 'destroy'])->name('destroy');
            Route::post('/{mobilityRequest}/publish', [MobilityRequestStatusController::class, 'publish'])->name('publish');
            Route::post('/{mobilityRequest}/pause', [MobilityRequestStatusController::class, 'pause'])->name('pause');
            Route::post('/{mobilityRequest}/renew', [MobilityRequestStatusController::class, 'renew'])->name('renew');
            Route::post('/{mobilityRequest}/close', [MobilityRequestStatusController::class, 'close'])->name('close');
        });

    Route::prefix('matches')->name('api.v1.matches.')
        ->middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value])
        ->group(function (): void {
            Route::get('/', [MatchController::class, 'index'])->name('index');
            Route::get('/{match}', [MatchController::class, 'show'])->name('show');
            Route::post('/{match}/invitation', [MatchParticipationController::class, 'invite'])->middleware('idempotent')->name('invitation.store');
            Route::post('/{match}/acceptance', [MatchParticipationController::class, 'accept'])->middleware('idempotent')->name('acceptance.store');
            Route::post('/{match}/decline', [MatchParticipationController::class, 'decline'])->middleware('idempotent')->name('decline.store');
            Route::post('/{match}/withdrawal', [MatchParticipationController::class, 'withdraw'])->middleware('idempotent')->name('withdrawal.store');
            Route::post('/{match}/contact-consent', [MatchParticipationController::class, 'grantContactConsent'])->middleware('idempotent')->name('contact-consent.store');
            Route::delete('/{match}/contact-consent', [MatchParticipationController::class, 'revokeContactConsent'])->name('contact-consent.destroy');
            Route::get('/{match}/contact', [MatchParticipationController::class, 'contact'])->name('contact.show');
            Route::post('/{match}/progress', [MatchParticipationController::class, 'progress'])->middleware('idempotent')->name('progress.store');
            Route::get('/{match}/messages', [MatchMessageController::class, 'index'])->name('messages.index');
            Route::post('/{match}/messages', [MatchMessageController::class, 'store'])->middleware('idempotent')->name('messages.store');
            Route::post('/{match}/messages/{message}/read-receipt', [MatchMessageController::class, 'read'])->middleware('idempotent')->name('messages.read-receipt.store');
            Route::post('/{match}/block', [MatchTrustController::class, 'block'])->middleware('idempotent')->name('block.store');
            Route::delete('/{match}/block', [MatchTrustController::class, 'unblock'])->name('block.destroy');
            Route::post('/{match}/reports', [MatchTrustController::class, 'report'])->middleware('idempotent')->name('reports.store');
        });

    Route::prefix('admin')->name('api.v1.admin.')
        ->middleware(['auth:sanctum', 'account.active', 'abilities:'.TokenAbility::AccessApi->value])
        ->group(function (): void {
            Route::get('/statistics', StatisticsController::class)->name('statistics.show');
            Route::get('/audit-logs', AuditLogIndexController::class)->name('audit-logs.index');
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::patch('/reports/{report}', [ReportController::class, 'update'])->name('reports.update');
            Route::get('/users', UserIndexController::class)
                ->name('users.index');
            Route::post('/users/{userPublicId}/suspension', UserSuspensionController::class)
                ->middleware('idempotent')
                ->name('users.suspension.store');
            Route::post('/users/{userPublicId}/reinstatement', UserReinstatementController::class)
                ->middleware('idempotent')
                ->name('users.reinstatement.store');
        });
});
