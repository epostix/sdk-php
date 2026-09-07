<?php

declare(strict_types=1);

namespace Epostix;

final readonly class RateLimitSnapshot
{
    public function __construct(
        public ?int $limit = null,
        public ?int $remaining = null,
        public ?int $resetAfterSeconds = null,
        public ?int $policyBurst = null,
        public ?int $policyWindowSeconds = null,
        public ?string $scope = null,
        public ?string $rawPolicy = null,
    ) {}
}
