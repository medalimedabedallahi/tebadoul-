<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\Admin\AccountNotSuspendedException;
use App\Exceptions\Auth\AccountSuspendedException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\InvalidVerificationCodeException;
use App\Exceptions\Matching\ContactNotAuthorizedException;
use App\Exceptions\Matching\InvalidMatchTransitionException;
use App\Exceptions\MobilityRequests\InvalidRequestTransitionException;
use App\Exceptions\MobilityRequests\RequestNotPublishableException;
use App\Exceptions\Trust\InteractionBlockedException;
use App\Exceptions\Trust\InvalidReportTransitionException;
use App\Exceptions\Trust\ReportAlreadyExistsException;
use App\Http\Responses\ApiError;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Renders every exception raised under `api/*` with the uniform JSON error body.
 *
 * Messages are generic and localized; details (route, model, SQL, trace) are only exposed
 * under a `debug` key when `app.debug` is enabled, which is refused in production.
 */
class ApiExceptionRenderer
{
    /**
     * Returns null for requests outside `api/*` so that the default renderer keeps handling them.
     */
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') || $e instanceof HttpResponseException) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return ApiError::make(
                $request,
                $e->status,
                ApiErrorCode::ValidationFailed,
                $e->getMessage() !== '' ? $e->getMessage() : null,
                $e->errors(),
            );
        }

        if ($e instanceof InvalidCredentialsException) {
            return ApiError::make($request, 401, ApiErrorCode::InvalidCredentials);
        }

        if ($e instanceof AccountSuspendedException) {
            return ApiError::make($request, 403, ApiErrorCode::AccountSuspended);
        }

        if ($e instanceof InvalidVerificationCodeException) {
            return ApiError::make($request, 422, ApiErrorCode::InvalidVerificationCode);
        }

        if ($e instanceof AccountNotSuspendedException) {
            return ApiError::make($request, 409, ApiErrorCode::AccountNotSuspended);
        }

        if ($e instanceof InvalidRequestTransitionException) {
            return ApiError::make($request, 409, ApiErrorCode::InvalidRequestTransition);
        }

        if ($e instanceof RequestNotPublishableException) {
            return ApiError::make($request, 409, ApiErrorCode::RequestNotPublishable, errors: $e->errors);
        }

        if ($e instanceof InvalidMatchTransitionException) {
            return ApiError::make($request, 409, ApiErrorCode::InvalidMatchTransition);
        }

        if ($e instanceof ContactNotAuthorizedException) {
            return ApiError::make($request, 403, ApiErrorCode::ContactNotAuthorized);
        }

        if ($e instanceof InteractionBlockedException) {
            return ApiError::make($request, 403, ApiErrorCode::InteractionBlocked);
        }

        if ($e instanceof ReportAlreadyExistsException) {
            return ApiError::make($request, 409, ApiErrorCode::ReportAlreadyExists);
        }

        if ($e instanceof InvalidReportTransitionException) {
            return ApiError::make($request, 409, ApiErrorCode::InvalidReportTransition);
        }

        if ($e instanceof AuthenticationException) {
            return ApiError::make(
                $request,
                401,
                ApiErrorCode::Unauthenticated,
                headers: ['WWW-Authenticate' => 'Bearer'],
            );
        }

        if ($e instanceof HttpExceptionInterface) {
            return ApiError::make(
                $request,
                $e->getStatusCode(),
                ApiErrorCode::fromStatus($e->getStatusCode()),
                headers: $this->headersOf($e),
            );
        }

        return $this->serverError($request, $e);
    }

    private function serverError(Request $request, Throwable $e): JsonResponse
    {
        $debug = config('app.debug') ? [
            'exception' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ] : [];

        return ApiError::make($request, 500, ApiErrorCode::ServerError, debug: $debug);
    }

    /**
     * Headers carried by the exception (Retry-After, Allow, X-RateLimit-*), as plain strings.
     *
     * @return array<string, string>
     */
    private function headersOf(HttpExceptionInterface $e): array
    {
        return array_map(
            static fn (array|string $value): string => is_array($value) ? implode(', ', $value) : $value,
            $e->getHeaders(),
        );
    }
}
