<?php

namespace App\Http\Responses;

use App\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Builds the uniform error body of the JSON API:
 * `{ "message": string, "code": string, "errors"?: { field: [string] }, "request_id": string|null }`,
 * plus a `debug` object when the caller supplies one (never in production).
 */
final class ApiError
{
    /**
     * @param  array<string, list<string>>  $errors  Validation messages keyed by field, when applicable.
     * @param  array<string, string>  $headers  Extra response headers (Retry-After, Allow, ...).
     * @param  array<string, mixed>  $debug  Failure details, only ever given when `app.debug` is enabled.
     */
    public static function make(
        Request $request,
        int $status,
        ApiErrorCode $code,
        ?string $message = null,
        array $errors = [],
        array $headers = [],
        array $debug = [],
    ): JsonResponse {
        $body = [
            'message' => $message ?? $code->message(),
            'code' => $code->value,
        ];

        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        $body['request_id'] = $request->attributes->get('request_id');

        if ($debug !== []) {
            $body['debug'] = $debug;
        }

        return response()->json($body, $status, $headers);
    }
}
