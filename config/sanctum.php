<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

/*
 * Host (with its port when present) of an absolute URL, in the format expected by "stateful".
 */
$hostOf = static function (?string $url): ?string {
    $host = parse_url((string) $url, PHP_URL_HOST);

    if (! is_string($host) || $host === '') {
        return null;
    }

    $port = parse_url((string) $url, PHP_URL_PORT);

    return $port === null ? $host : $host.':'.$port;
};

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests coming from these hosts receive cookie-based (SPA) authentication.
    | There is no built-in "localhost" list: without SANCTUM_STATEFUL_DOMAINS
    | (comma-separated hosts, with the port when needed), only the hosts of
    | APP_URL and FRONTEND_URL are trusted. Every other client uses bearer tokens.
    |
    */

    'stateful' => array_values(array_unique(array_filter(
        filled(env('SANCTUM_STATEFUL_DOMAINS'))
            ? array_map('trim', explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS')))
            : [$hostOf(env('APP_URL')), $hostOf(env('FRONTEND_URL'))]
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Number of minutes until an issued token is considered expired. Defaults
    | to 7 days. Expired tokens are removed daily by "sanctum:prune-expired"
    | (see routes/console.php). First-party sessions are not affected.
    |
    */

    'expiration' => (int) env('SANCTUM_EXPIRATION', 60 * 24 * 7),

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | The "bdl_" prefix makes Badal tokens recognizable by secret-scanning
    | tools (GitHub secret scanning, gitleaks, ...) if one is ever leaked.
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'bdl_'),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
