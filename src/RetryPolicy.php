<?php

declare(strict_types=1);

namespace Epostix;

final readonly class RetryPolicy
{
    public function __construct(
        public int $maximumRetries = 2,
        public float $operationDeadlineMilliseconds = 30000.0,
        public float $connectTimeoutMilliseconds = 5000.0,
        public float $readTimeoutMilliseconds = 30000.0,
        public float $backoffBaseMilliseconds = 250.0,
        public float $backoffMaximumMilliseconds = 2000.0,
        public float $backoffMultiplier = 2.0,
        public bool $honorRetryAfter = true,
        public MutationRetryMode $mutationRetry = MutationRetryMode::OnlyWhenDeduplicated,
    ) {}

    public static function default(): self
    {
        return new self();
    }

    public static function disabled(): self
    {
        return new self(maximumRetries: 0);
    }

    public static function upload(): self
    {
        return new self(
            operationDeadlineMilliseconds: 180000.0,
            connectTimeoutMilliseconds: 30000.0,
            readTimeoutMilliseconds: 180000.0,
        );
    }

    public static function batch(): self
    {
        return new self(
            operationDeadlineMilliseconds: 90000.0,
            readTimeoutMilliseconds: 90000.0,
        );
    }
}
