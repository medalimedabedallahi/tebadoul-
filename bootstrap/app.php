<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\QueryExceptionReporter;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ?: null);

        // Global, so that unmatched routes (404) and wrong methods (405) also carry a request id.
        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [SetLocale::class]);
        $middleware->api(append: [SetLocale::class]);

        // A guest on the API gets a 401 (rendered by ApiExceptionRenderer), never a redirect to a login page.
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*') || ! Route::has('login') ? null : route('login'),
        );

        // A signed-in visitor of a `guest` web route (login, register, ...) is sent to their
        // account instead; never applies to the API, which has no `guest` middleware group.
        $middleware->redirectUsersTo(
            fn (Request $request): ?string => $request->is('api/*') || ! Route::has('account.show') ? null : route('account.show'),
        );

        $middleware->alias([
            'idempotent' => EnsureIdempotency::class,
            // Sanctum token abilities: `abilities:a,b` requires all of them, `ability:a,b` requires any.
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            // Web and API: requires a fully active account, re-checked on every request (see the class doc).
            'account.active' => EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(new ApiExceptionRenderer);

        $exceptions->report(new QueryExceptionReporter)->stop();
    })->create();
