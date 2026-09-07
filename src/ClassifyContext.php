<?php

declare(strict_types=1);

namespace Epostix;

final readonly class ClassifyContext
{
    public function __construct(
        public RetryPolicy $policy,
        public RetryClass $retryClass,
        public int $attemptNumber,
        public float $remainingMilliseconds,
        public ?\Throwable $error,
        public bool $hasIdempotencyKey,
        public bool $bodyIsReplayable,
        public bool $bytesWereWritten,
        public bool $responseStarted,
        public RandomSource $random,
    ) {}
}
