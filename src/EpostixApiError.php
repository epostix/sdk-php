<?php

declare(strict_types=1);

namespace Epostix;

class EpostixApiError extends EpostixError
{
    public function __construct(
        public readonly int $status,
        public readonly string $type,
        public readonly bool $isKnownType,
        public readonly string $apiMessage,
        public readonly string $requestId,
        public readonly ?string $docUrl,
        public readonly array $details,
        public readonly ResponseMetadata $responseMetadata,
        public readonly ?string $rawBodySnippet = null,
        public readonly bool $rawBodyTruncated = false,
    ) {
        $label = $type === '' ? 'http_' . $status : $type;

        parent::__construct(
            $label . ': ' . $apiMessage . ' (status=' . $status . ', request_id=' . $requestId . ')',
        );
    }

    public static function build(
        int $status,
        string $type,
        string $apiMessage,
        string $requestId,
        ?string $docUrl,
        array $details,
        ResponseMetadata $responseMetadata,
        ?string $rawBodySnippet = null,
        bool $rawBodyTruncated = false,
    ): self {
        $known = array_key_exists($type, EpostixErrorType::BY_CLASS);
        $class = $known ? EpostixErrorType::BY_CLASS[$type] : self::classForStatus($status);

        return new $class(
            $status,
            $type,
            $known,
            $apiMessage,
            $requestId,
            $docUrl,
            $details,
            $responseMetadata,
            $rawBodySnippet,
            $rawBodyTruncated,
        );
    }

    public function isRetryable(): bool
    {
        if (in_array($this->type, EpostixErrorType::NEVER_RETRY, true)) {
            return false;
        }

        if ($this->type === EpostixErrorType::RATE_LIMIT_EXCEEDED
            || $this->type === EpostixErrorType::IDEMPOTENCY_IN_PROGRESS
            || $this->type === EpostixErrorType::ATTACHMENT_FETCH_FAILED) {
            return true;
        }

        return $this->status === 408 || $this->status >= 500;
    }

    private static function classForStatus(int $status): string
    {
        return match (true) {
            $status === 401 => EpostixAuthenticationError::class,
            $status === 403 => EpostixPermissionError::class,
            $status === 404 => EpostixNotFoundError::class,
            $status === 409 => EpostixConflictError::class,
            $status === 429 => EpostixRateLimitError::class,
            $status >= 500 => EpostixServerError::class,
            $status >= 400 => EpostixInvalidRequestError::class,
            default => self::class,
        };
    }
}
