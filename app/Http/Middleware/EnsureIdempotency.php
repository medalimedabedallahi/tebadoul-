<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Http\Responses\ApiError;
use App\Support\DerivedKey;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Makes a POST safe to retry when the client sends an `Idempotency-Key` header (alias `idempotent`).
 *
 * The first request claims the key with an atomic `add()` and runs normally. While it runs, a
 * concurrent request with the same key receives 409. Once it has succeeded (2xx), any request with
 * the same key and the same payload receives the stored response again, flagged with
 * `Idempotent-Replayed: true`, without running the action a second time. The same key with another
 * payload receives 422. Failed responses are never stored, so the client can retry after fixing
 * the problem. A request without the header is not affected.
 *
 * Scope of a key: the authenticated account; for a guest, the IP address AND the fingerprint of
 * the payload, so that two guests behind the same address never share a stored response unless
 * they sent exactly the same request (a guest therefore never gets the 422 "reused key" answer: a
 * different payload is simply a different key).
 *
 * Fingerprint: an HMAC (key derived from `app.key` for this purpose only, see {@see DerivedKey})
 * of the method, the path and the canonical payload WITHOUT the secret fields ({@see self::SECRET_FIELDS}):
 * no password or code ever influences a cache key, even hashed.
 *
 * Never stores a credential: a response that carries a token (a `token`-like field, or the Sanctum
 * token prefix anywhere in the body) is returned but not stored, and the sign-in route is excluded
 * outright. Put the middleware after `auth:sanctum` and the throttles, and only on endpoints whose
 * response may sit in the cache for a day.
 *
 * Keys live in the same never-evicted store as the rate limiters (`cache.limiter`).
 */
class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    public const REPLAY_HEADER = 'Idempotent-Replayed';

    /**
     * Seconds during which a claimed key is considered in progress (crash protection).
     */
    private const IN_PROGRESS_TTL = 60;

    /**
     * Seconds during which the response of a completed request can be replayed.
     */
    private const COMPLETED_TTL = 86400;

    /**
     * Request fields left out of the fingerprint: secrets never feed a cache key.
     *
     * @var list<string>
     */
    private const SECRET_FIELDS = ['password', 'password_confirmation', 'current_password', 'code'];

    /**
     * Response fields that carry a credential: such a response is never stored.
     *
     * @var list<string>
     */
    private const TOKEN_FIELDS = ['token', 'access_token', 'refresh_token', 'plain_text_token', 'plainTextToken'];

    /**
     * Routes that issue credentials: the middleware never applies to them.
     *
     * @var list<string>
     */
    private const EXCLUDED_ROUTES = ['api.v1.auth.login'];

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! $request->isMethod('POST') || $key === null || $request->routeIs(...self::EXCLUDED_ROUTES)) {
            return $next($request);
        }

        if (preg_match('/^[A-Za-z0-9_-]{8,128}$/', $key) !== 1) {
            return ApiError::make($request, 400, ApiErrorCode::IdempotencyKeyInvalid);
        }

        $fingerprint = $this->fingerprint($request);
        $cacheKey = $this->cacheKey($request, $key, $fingerprint);

        if ($this->store()->add($cacheKey, ['state' => 'processing', 'fingerprint' => $fingerprint], self::IN_PROGRESS_TTL)) {
            return $this->run($request, $next, $cacheKey, $fingerprint);
        }

        return $this->answerExistingKey($request, $cacheKey, $fingerprint);
    }

    /**
     * Run the request and keep its response when it succeeded and carries no credential.
     *
     * @param  Closure(Request): Response  $next
     */
    private function run(Request $request, Closure $next, string $cacheKey, string $fingerprint): Response
    {
        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $this->store()->forget($cacheKey);

            throw $e;
        }

        if (! $response->isSuccessful()
            || $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || $this->carriesCredential((string) $response->getContent())) {
            $this->store()->forget($cacheKey);

            return $response;
        }

        $this->store()->put($cacheKey, [
            'state' => 'completed',
            'fingerprint' => $fingerprint,
            'status' => $response->getStatusCode(),
            'content' => (string) $response->getContent(),
            'content_type' => $response->headers->get('Content-Type'),
        ], self::COMPLETED_TTL);

        return $response;
    }

    private function answerExistingKey(Request $request, string $cacheKey, string $fingerprint): Response
    {
        $record = $this->store()->get($cacheKey);

        if (is_array($record) && ($record['fingerprint'] ?? null) !== $fingerprint) {
            return ApiError::make($request, 422, ApiErrorCode::IdempotencyKeyReused);
        }

        if (! is_array($record) || ($record['state'] ?? null) !== 'completed') {
            return ApiError::make(
                $request,
                409,
                ApiErrorCode::IdempotencyRequestInProgress,
                headers: ['Retry-After' => '1'],
            );
        }

        return new Response(
            $record['content'],
            $record['status'],
            array_filter([
                'Content-Type' => $record['content_type'],
                self::REPLAY_HEADER => 'true',
            ]),
        );
    }

    private function cacheKey(Request $request, string $key, string $fingerprint): string
    {
        $user = $request->user('sanctum');
        $scope = $user !== null
            ? 'user:'.$user->getAuthIdentifier()
            : 'guest:'.$request->ip().'|'.$fingerprint;

        return 'idempotency:'.DerivedKey::hmac(DerivedKey::IDEMPOTENCY, "key\n".$scope."\n".$request->path()."\n".$key);
    }

    private function fingerprint(Request $request): string
    {
        $payload = $this->canonical($this->withoutSecrets($request->input()));

        return DerivedKey::hmac(
            DerivedKey::IDEMPOTENCY,
            "fingerprint\n".$request->method()."\n".$request->path()."\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    private function withoutSecrets(array $input): array
    {
        foreach ($input as $field => $value) {
            if (is_string($field) && in_array($field, self::SECRET_FIELDS, true)) {
                unset($input[$field]);
            } elseif (is_array($value)) {
                $input[$field] = $this->withoutSecrets($value);
            }
        }

        return $input;
    }

    /**
     * The payload with its object keys sorted, so that `{"a":1,"b":2}` and `{"b":2,"a":1}` match.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<array-key, mixed>
     */
    private function canonical(array $input): array
    {
        if (! array_is_list($input)) {
            ksort($input, SORT_STRING);
        }

        foreach ($input as $field => $value) {
            if (is_array($value)) {
                $input[$field] = $this->canonical($value);
            }
        }

        return $input;
    }

    /**
     * Whether a response body carries a credential: the Sanctum token prefix anywhere, or a
     * token-like field at any depth of a JSON body.
     */
    private function carriesCredential(string $content): bool
    {
        $prefix = (string) config('sanctum.token_prefix', '');

        if ($prefix !== '' && str_contains($content, $prefix)) {
            return true;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) && $this->hasTokenField($decoded);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function hasTokenField(array $data): bool
    {
        foreach ($data as $field => $value) {
            if (is_string($field) && in_array($field, self::TOKEN_FIELDS, true)) {
                return true;
            }

            if (is_array($value) && $this->hasTokenField($value)) {
                return true;
            }
        }

        return false;
    }

    private function store(): Repository
    {
        return Cache::store(config('cache.limiter'));
    }
}
