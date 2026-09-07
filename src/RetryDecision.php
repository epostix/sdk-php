<?php

declare(strict_types=1);

namespace Epostix;

final readonly class RetryDecision
{
    private function __construct(
        public bool $shouldRetry,
        public float $delayMilliseconds,
        public ?string $reason,
    ) {}

    public static function again(float $delayMilliseconds): self
    {
        return new self(true, $delayMilliseconds, null);
    }

    public static function stop(string $reason): self
    {
        return new self(false, 0.0, $reason);
    }
}
