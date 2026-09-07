<?php

declare(strict_types=1);

namespace Epostix;

final readonly class AttemptRecord
{
    public function __construct(
        public int $attemptNumber,
        public float $startedAtMilliseconds,
        public float $elapsedMilliseconds,
        public ?int $statusCode = null,
        public ?string $errorType = null,
        public ?int $retryAfterSeconds = null,
        public float $sleptBeforeMilliseconds = 0.0,
    ) {}
}
