<?php

declare(strict_types=1);

namespace Epostix;

final readonly class OperationSpec
{
    public function __construct(
        public string $id,
        public string $method,
        public array $pathLiterals,
        public array $pathParams,
        public int $successStatus,
        public bool $empty,
        public bool $binary,
        public bool $idempotent,
        public RetryClass $retryClass,
    ) {}
}
