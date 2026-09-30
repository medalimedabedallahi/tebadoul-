<?php

namespace App\Enums;

/**
 * Stable, machine-readable error identifiers of the JSON API (the `code` field of an error).
 *
 * Clients branch on the code, never on the human-readable message, which is localized.
 */
enum ApiErrorCode: string
{
    case BadRequest = 'bad_request';
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case Conflict = 'conflict';
    case CsrfTokenMismatch = 'csrf_token_mismatch';
    case ValidationFailed = 'validation_failed';
    case TooManyRequests = 'too_many_requests';
    case ServerError = 'server_error';
    case ServiceUnavailable = 'service_unavailable';
    case HttpError = 'http_error';
    case IdempotencyKeyInvalid = 'idempotency_key_invalid';
    case IdempotencyKeyReused = 'idempotency_key_reused';
    case IdempotencyRequestInProgress = 'idempotency_request_in_progress';
    case InvalidCredentials = 'invalid_credentials';
    case AccountSuspended = 'account_suspended';
    case InvalidVerificationCode = 'invalid_verification_code';
    case AccountNotSuspended = 'account_not_suspended';
    case InvalidRequestTransition = 'invalid_request_transition';
    case RequestNotPublishable = 'request_not_publishable';
    case InvalidMatchTransition = 'invalid_match_transition';
    case ContactNotAuthorized = 'contact_not_authorized';
    case InteractionBlocked = 'interaction_blocked';
    case ReportAlreadyExists = 'report_already_exists';
    case InvalidReportTransition = 'invalid_report_transition';

    /**
     * The code describing an HTTP status raised by the framework or by `abort()`.
     */
    public static function fromStatus(int $status): self
    {
        return match ($status) {
            400 => self::BadRequest,
            401 => self::Unauthenticated,
            403 => self::Forbidden,
            404 => self::NotFound,
            405 => self::MethodNotAllowed,
            409 => self::Conflict,
            419 => self::CsrfTokenMismatch,
            422 => self::ValidationFailed,
            429 => self::TooManyRequests,
            503 => self::ServiceUnavailable,
            default => $status >= 500 ? self::ServerError : self::HttpError,
        };
    }

    /**
     * Generic message for the code. It never contains details about the failure, so nothing
     * about routes, models, queries or personal data can leak through it.
     */
    public function message(): string
    {
        return match ($this) {
            self::BadRequest => __('Bad request.'),
            self::Unauthenticated => __('Unauthenticated.'),
            self::Forbidden => __('This action is unauthorized.'),
            self::NotFound => __('Resource not found.'),
            self::MethodNotAllowed => __('Method not allowed.'),
            self::Conflict => __('The request conflicts with the current state of the resource.'),
            self::CsrfTokenMismatch => __('Page expired.'),
            self::ValidationFailed => __('The given data was invalid.'),
            self::TooManyRequests => __('Too many requests.'),
            self::ServerError => __('Server error.'),
            self::ServiceUnavailable => __('Service unavailable.'),
            self::HttpError => __('The request could not be processed.'),
            self::IdempotencyKeyInvalid => __('The Idempotency-Key header is invalid.'),
            self::IdempotencyKeyReused => __('The Idempotency-Key was already used with a different request.'),
            self::IdempotencyRequestInProgress => __('A request with this Idempotency-Key is still being processed.'),
            self::InvalidCredentials => __('These credentials do not match our records.'),
            self::AccountSuspended => __('This account is suspended.'),
            self::InvalidVerificationCode => __('The verification code is invalid or has expired.'),
            self::AccountNotSuspended => __('This account is not suspended.'),
            self::InvalidRequestTransition => __('This operation is not allowed in the current status of the request.'),
            self::RequestNotPublishable => __('The request cannot be published as it is.'),
            self::InvalidMatchTransition => __('This operation is not allowed in the current status of the match.'),
            self::ContactNotAuthorized => __('Contact details are only shared when both participants agree to it.'),
            self::InteractionBlocked => __('This interaction is not available.'),
            self::ReportAlreadyExists => __('You have already reported this participant for this match.'),
            self::InvalidReportTransition => __('This report has already been reviewed.'),
        };
    }
}
