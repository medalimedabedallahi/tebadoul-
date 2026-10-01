<?php

namespace App\Providers;

use App\Contracts\ContactCodeSender;
use App\Contracts\MatchingRules;
use App\Exceptions\InsecureConfigurationException;
use App\Support\ContactCodes\EmailContactCodeSender;
use App\Support\Matching\StrictRulesV1;
use App\Support\RateLimiting\AuthRateLimits;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Environments exposed to real people, where the insecure settings below are refused at boot.
     *
     * @var list<string>
     */
    private const EXPOSED_ENVIRONMENTS = ['production', 'staging'];

    /**
     * Mailers that never deliver anything: in an exposed environment, a verification code sent
     * through them is lost (array) or written in clear to the logs (log).
     *
     * @var list<string>
     */
    private const NON_DELIVERING_MAILERS = ['log', 'array'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ContactCodeSender::class, EmailContactCodeSender::class);
        // The rules version in force; a new version is a new class, so old matches stay explainable.
        $this->app->bind(MatchingRules::class, StrictRulesV1::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->refuseInsecureConfigurationWhenExposed();
        $this->configurePasswordDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Password policy used by `Password::defaults()`: at least 12 characters, and in production
     * not found in known data breaches (the check calls the haveibeenpwned range API with a
     * k-anonymity prefix; it is skipped elsewhere so that development and tests need no network).
     */
    private function configurePasswordDefaults(): void
    {
        Password::defaults(
            fn (): Password => $this->app->isProduction()
                ? Password::min(12)->uncompromised()
                : Password::min(12)
        );
    }

    /**
     * Refuse to start a production or staging application with a setting that leaks secrets or
     * silently drops the verification codes:
     *
     * - `APP_DEBUG=true`: debug pages expose environment variables, secrets and stack traces;
     * - `MAIL_MAILER=log` or `array`: no email is delivered (and `log` writes the code in clear).
     *
     * @throws InsecureConfigurationException
     */
    private function refuseInsecureConfigurationWhenExposed(): void
    {
        if (! $this->app->environment(self::EXPOSED_ENVIRONMENTS)) {
            return;
        }

        $environment = $this->app->environment();

        if (config('app.debug')) {
            throw new InsecureConfigurationException(
                "APP_DEBUG must be false when APP_ENV is $environment: refusing to boot the application."
            );
        }

        if (in_array(config('mail.default'), self::NON_DELIVERING_MAILERS, true)) {
            throw new InsecureConfigurationException(sprintf(
                'MAIL_MAILER must not be one of [%s] when APP_ENV is %s: refusing to boot the application.',
                implode(', ', self::NON_DELIVERING_MAILERS),
                $environment,
            ));
        }
    }

    /**
     * Define the named rate limiters used by the `throttle:<name>` middleware.
     *
     * The limits themselves are defined once, in {@see AuthRateLimits} (see it for the numbers),
     * and shared with the Livewire components. Each limiter below reads exactly ONE explicit
     * request field (two for `register`): adding another field to a request never changes which
     * value keys the limit.
     *
     * - `api` (120 per minute): every API route. Keyed by the user id when the request is
     *   authenticated through Sanctum, by IP otherwise.
     * - `login`: the `identifier` field. `otp-send`, `otp-verify`, `password-reset`: the
     *   `contact` field. `register`: the `email` and `phone` fields. `password-update`: the
     *   authenticated account.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $user = $request->user('sanctum');

            return Limit::perMinute(120)->by(
                $user !== null ? 'user:'.$user->getAuthIdentifier() : 'ip:'.$request->ip()
            );
        });

        RateLimiter::for('login', fn (Request $request): array => $this->limits()->login(
            $this->stringInput($request, 'identifier'),
            (string) $request->ip(),
        ));

        RateLimiter::for('otp-send', fn (Request $request): array => $this->limits()->otpSend(
            $this->stringInput($request, 'contact'),
            (string) $request->ip(),
        ));

        RateLimiter::for('register', fn (Request $request): array => $this->limits()->register(
            $this->stringInput($request, 'email'),
            $this->stringInput($request, 'phone'),
            (string) $request->ip(),
        ));

        RateLimiter::for('otp-verify', fn (Request $request): array => $this->limits()->otpVerify(
            $this->stringInput($request, 'contact'),
            (string) $request->ip(),
        ));

        RateLimiter::for('password-reset', fn (Request $request): array => $this->limits()->passwordReset(
            $this->stringInput($request, 'contact'),
            (string) $request->ip(),
        ));

        RateLimiter::for('password-update', fn (Request $request): array => $this->limits()->passwordUpdate(
            $request->user()?->getAuthIdentifier(),
            (string) $request->ip(),
        ));
    }

    private function limits(): AuthRateLimits
    {
        return $this->app->make(AuthRateLimits::class);
    }

    /**
     * The value of one body field when it is a string; anything else (array, number, absent) keys
     * nothing, so the per-IP limits alone apply.
     */
    private function stringInput(Request $request, string $field): ?string
    {
        $value = $request->input($field);

        return is_string($value) ? $value : null;
    }
}
