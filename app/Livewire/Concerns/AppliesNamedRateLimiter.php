<?php

namespace App\Livewire\Concerns;

use App\Support\RateLimiting\AuthRateLimits;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Applies, from a Livewire component, the limits of a named limiter (`login`, `otp-send`,
 * `register`, `otp-verify`, `password-reset`, `password-update`).
 *
 * The `throttle:<name>` middleware cannot be used on the Livewire update route, whose JSON payload
 * has none of the flat fields the named limiters read. The component instead computes the limits
 * with {@see AuthRateLimits} from ITS OWN validated value (for example
 * `$limits->otpSend($validated['contact'], request()->ip())`) and passes them here. Nothing in this
 * trait reads the request input: neither the query string nor the body of `/livewire/update` can
 * change which contact keys the limit.
 *
 * Counters are stored under the same keys as the middleware (`md5(<limiter name>.<limit key>)`),
 * so the API and the web share the same buckets.
 */
trait AppliesNamedRateLimiter
{
    /**
     * Throws a {@see ValidationException} on `$field` when one of `$limits` is currently exceeded;
     * otherwise records one attempt against every one of them.
     *
     * @param  string  $limiterName  Name of the limiter the limits belong to (e.g. `otp-send`).
     * @param  list<Limit>  $limits  Limits computed by {@see AuthRateLimits} from the component's value.
     */
    protected function applyNamedRateLimiter(string $limiterName, array $limits, string $field, string $messageKey): void
    {
        $keyed = [];

        foreach ($limits as $limit) {
            $keyed[md5($limiterName.$limit->key)] = $limit;
        }

        foreach ($keyed as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit->maxAttempts)) {
                throw ValidationException::withMessages([
                    $field => __($messageKey, ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        foreach ($keyed as $key => $limit) {
            RateLimiter::hit($key, $limit->decaySeconds);
        }
    }

    protected function authRateLimits(): AuthRateLimits
    {
        return app(AuthRateLimits::class);
    }
}
