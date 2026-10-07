<?php

use App\Http\Controllers\AdvertisementImageController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\SwitchLocaleController;
use App\Livewire\Account\Show as AccountShow;
use App\Livewire\Admin\Advertisements\Index as AdminAdvertisementsIndex;
use App\Livewire\Admin\AuditLogs\Index as AdminAuditLogsIndex;
use App\Livewire\Admin\Reports\Index as AdminReportsIndex;
use App\Livewire\Admin\Statistics\Show as AdminStatisticsShow;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\SetNewPassword;
use App\Livewire\Auth\VerifyContactCode;
use App\Livewire\Matches\Index as MatchesIndex;
use App\Livewire\Matches\Show as MatchesShow;
use App\Livewire\MobilityRequests\Edit as RequestsEdit;
use App\Livewire\MobilityRequests\Index as RequestsIndex;
use App\Livewire\MobilityRequests\Show as RequestsShow;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Profile\Edit as ProfileEdit;
use App\Models\Advertisement;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::post('/locale', SwitchLocaleController::class)->name('locale.switch');

Route::view('/conditions-utilisation', 'legal.show', ['document' => 'terms'])->name('legal.terms');
Route::view('/confidentialite', 'legal.show', ['document' => 'privacy'])->name('legal.privacy');

// Public: the controller only serves the banner of a displayable advertisement to non-administrators.
Route::get('/publicites/{advertisement}/image', AdvertisementImageController::class)->name('advertisements.image');

Route::middleware('guest')->group(function (): void {
    Route::get('/inscription', Register::class)->name('register');
    Route::get('/connexion', Login::class)->name('login');
    Route::get('/mot-de-passe-oublie', ForgotPassword::class)->name('password.request');
    Route::get('/reinitialiser-mot-de-passe', SetNewPassword::class)->name('password.reset');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('google.redirect');
    Route::get('/inscription/google', [GoogleAuthController::class, 'registration'])->name('google.register');
    Route::post('/inscription/google', [GoogleAuthController::class, 'store'])->middleware('throttle:10,1')->name('google.register.store');
});

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('google.callback');

// Reachable whether or not the visitor is signed in: verifying a contact never requires nor
// grants a session by itself (an account pending verification is never signed in, see Login).
Route::get('/verification-contact', VerifyContactCode::class)->name('verification.show');

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::get('/mon-compte', AccountShow::class)->name('account.show');
    Route::post('/mon-compte/google', [GoogleAuthController::class, 'link'])->middleware('throttle:10,1')->name('google.link');
    Route::get('/mon-profil', ProfileEdit::class)->name('profile.edit');

    Route::get('/mes-demandes', RequestsIndex::class)->name('requests.index');
    Route::get('/mes-demandes/nouvelle', RequestsEdit::class)->name('requests.create');
    Route::get('/mes-demandes/{mobilityRequest}', RequestsShow::class)->name('requests.show');
    Route::get('/mes-demandes/{mobilityRequest}/modifier', RequestsEdit::class)->name('requests.edit');

    Route::get('/mes-correspondances', MatchesIndex::class)->name('matches.index');
    Route::get('/mes-correspondances/{match}', MatchesShow::class)->name('matches.show');

    Route::get('/notifications', NotificationsIndex::class)->name('notifications.index');

    // The component's actions authorize again; this only keeps the page itself for administrators.
    Route::get('/administration/comptes', AdminUsersIndex::class)
        ->middleware('can:viewAny,'.User::class)
        ->name('admin.users.index');
    Route::get('/moderation/signalements', AdminReportsIndex::class)
        ->middleware('can:viewAny,'.UserReport::class)
        ->name('admin.reports.index');
    Route::get('/administration/statistiques', AdminStatisticsShow::class)
        ->middleware('can:viewStatistics,'.User::class)
        ->name('admin.statistics.show');
    Route::get('/administration/journal-audit', AdminAuditLogsIndex::class)
        ->middleware('can:viewAny,'.AuditLog::class)
        ->name('admin.audit-logs.index');
    Route::get('/administration/publicites', AdminAdvertisementsIndex::class)
        ->middleware('can:viewAny,'.Advertisement::class)
        ->name('admin.advertisements.index');
});

// An unmatched URL otherwise skips the web middleware: the 404 page would ignore the visitor's
// session language. API paths keep their JSON 404.
Route::fallback(fn () => abort(404))->where('fallbackPlaceholder', '^(?!api(/|$)).*');
