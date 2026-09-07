<?php

declare(strict_types=1);

namespace Epostix;

final class ResponseMetadata
{
    public function __construct(
        public int $statusCode = 0,
        public string $requestId = '',
        public ?RateLimitSnapshot $rateLimit = null,
        public ?int $retryAfterSeconds = null,
        public bool $idempotentReplayed = false,
        public ?string $idempotencyKey = null,
        public int $attempts = 0,
        public float $elapsedMilliseconds = 0.0,
        public array $attemptRecords = [],
        public ?string $retriesExhaustedReason = null,
    ) {}
}
