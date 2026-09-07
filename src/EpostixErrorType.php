<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixErrorType
{
    public const INVALID_REQUEST = 'invalid_request';
    public const VALIDATION_ERROR = 'validation_error';
    public const UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';
    public const PAYLOAD_TOO_LARGE = 'payload_too_large';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const NOT_FOUND = 'not_found';
    public const AUTHENTICATION_FAILED = 'authentication_failed';
    public const API_KEY_EXPIRED = 'api_key_expired';
    public const INSUFFICIENT_SCOPE = 'insufficient_scope';
    public const API_KEY_IP_RESTRICTED = 'api_key_ip_restricted';
    public const DOMAIN_SCOPE_RESTRICTED = 'domain_scope_restricted';
    public const WORKSPACE_SUSPENDED = 'workspace_suspended';
    public const TEST_MODE_RESTRICTED = 'test_mode_restricted';
    public const DOMAIN_NOT_FOUND = 'domain_not_found';
    public const DOMAIN_NOT_VERIFIED = 'domain_not_verified';
    public const INVALID_FROM_ADDRESS = 'invalid_from_address';
    public const DUPLICATE_DETECTED = 'duplicate_detected';
    public const INVALID_SCHEDULE = 'invalid_schedule';
    public const EMAIL_NOT_CANCELLABLE = 'email_not_cancellable';
    public const BATCH_TOO_LARGE = 'batch_too_large';
    public const TEMPLATE_NOT_FOUND = 'template_not_found';
    public const TEMPLATE_RENDER_FAILED = 'template_render_failed';
    public const ATTACHMENT_NOT_FOUND = 'attachment_not_found';
    public const ATTACHMENT_EXPIRED = 'attachment_expired';
    public const ATTACHMENT_TOO_LARGE = 'attachment_too_large';
    public const ATTACHMENT_FETCH_FAILED = 'attachment_fetch_failed';
    public const RATE_LIMIT_EXCEEDED = 'rate_limit_exceeded';
    public const DAILY_QUOTA_EXCEEDED = 'daily_quota_exceeded';
    public const MONTHLY_QUOTA_EXCEEDED = 'monthly_quota_exceeded';
    public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';
    public const IDEMPOTENCY_IN_PROGRESS = 'idempotency_in_progress';
    public const API_KEY_IN_USE = 'api_key_in_use';
    public const WEBHOOK_LIMIT_REACHED = 'webhook_limit_reached';
    public const WEBHOOK_URL_NOT_ALLOWED = 'webhook_url_not_allowed';
    public const INTERNAL_ERROR = 'internal_error';
    public const SERVICE_UNAVAILABLE = 'service_unavailable';

    public const NEVER_RETRY = [
        self::AUTHENTICATION_FAILED,
        self::API_KEY_EXPIRED,
        self::INSUFFICIENT_SCOPE,
        self::API_KEY_IP_RESTRICTED,
        self::DOMAIN_SCOPE_RESTRICTED,
        self::TEST_MODE_RESTRICTED,
        self::WORKSPACE_SUSPENDED,
        self::VALIDATION_ERROR,
        self::INVALID_REQUEST,
        self::INVALID_FROM_ADDRESS,
        self::INVALID_SCHEDULE,
        self::BATCH_TOO_LARGE,
        self::PAYLOAD_TOO_LARGE,
        self::UNSUPPORTED_MEDIA_TYPE,
        self::METHOD_NOT_ALLOWED,
        self::EMAIL_NOT_CANCELLABLE,
        self::DUPLICATE_DETECTED,
        self::DOMAIN_NOT_VERIFIED,
        self::API_KEY_IN_USE,
        self::WEBHOOK_LIMIT_REACHED,
        self::WEBHOOK_URL_NOT_ALLOWED,
        self::NOT_FOUND,
        self::DOMAIN_NOT_FOUND,
        self::TEMPLATE_NOT_FOUND,
        self::ATTACHMENT_NOT_FOUND,
        self::ATTACHMENT_EXPIRED,
        self::ATTACHMENT_TOO_LARGE,
        self::TEMPLATE_RENDER_FAILED,
        self::IDEMPOTENCY_CONFLICT,
        self::DAILY_QUOTA_EXCEEDED,
        self::MONTHLY_QUOTA_EXCEEDED,
    ];

    public const BY_CLASS = [
        self::INVALID_REQUEST => EpostixInvalidRequestError::class,
        self::UNSUPPORTED_MEDIA_TYPE => EpostixInvalidRequestError::class,
        self::PAYLOAD_TOO_LARGE => EpostixInvalidRequestError::class,
        self::METHOD_NOT_ALLOWED => EpostixInvalidRequestError::class,
        self::VALIDATION_ERROR => EpostixValidationError::class,
        self::INVALID_FROM_ADDRESS => EpostixValidationError::class,
        self::INVALID_SCHEDULE => EpostixValidationError::class,
        self::BATCH_TOO_LARGE => EpostixValidationError::class,
        self::AUTHENTICATION_FAILED => EpostixAuthenticationError::class,
        self::API_KEY_EXPIRED => EpostixAuthenticationError::class,
        self::INSUFFICIENT_SCOPE => EpostixPermissionError::class,
        self::API_KEY_IP_RESTRICTED => EpostixPermissionError::class,
        self::DOMAIN_SCOPE_RESTRICTED => EpostixPermissionError::class,
        self::TEST_MODE_RESTRICTED => EpostixPermissionError::class,
        self::WORKSPACE_SUSPENDED => EpostixAccountSuspendedError::class,
        self::NOT_FOUND => EpostixNotFoundError::class,
        self::DOMAIN_NOT_FOUND => EpostixNotFoundError::class,
        self::TEMPLATE_NOT_FOUND => EpostixNotFoundError::class,
        self::ATTACHMENT_NOT_FOUND => EpostixNotFoundError::class,
        self::DUPLICATE_DETECTED => EpostixConflictError::class,
        self::EMAIL_NOT_CANCELLABLE => EpostixConflictError::class,
        self::DOMAIN_NOT_VERIFIED => EpostixConflictError::class,
        self::API_KEY_IN_USE => EpostixConflictError::class,
        self::WEBHOOK_LIMIT_REACHED => EpostixConflictError::class,
        self::WEBHOOK_URL_NOT_ALLOWED => EpostixConflictError::class,
        self::IDEMPOTENCY_CONFLICT => EpostixIdempotencyConflictError::class,
        self::IDEMPOTENCY_IN_PROGRESS => EpostixIdempotencyInProgressError::class,
        self::RATE_LIMIT_EXCEEDED => EpostixRateLimitError::class,
        self::DAILY_QUOTA_EXCEEDED => EpostixQuotaExceededError::class,
        self::MONTHLY_QUOTA_EXCEEDED => EpostixQuotaExceededError::class,
        self::TEMPLATE_RENDER_FAILED => EpostixContentError::class,
        self::ATTACHMENT_EXPIRED => EpostixContentError::class,
        self::ATTACHMENT_TOO_LARGE => EpostixContentError::class,
        self::ATTACHMENT_FETCH_FAILED => EpostixContentError::class,
        self::INTERNAL_ERROR => EpostixServerError::class,
        self::SERVICE_UNAVAILABLE => EpostixServerError::class,
    ];
}
