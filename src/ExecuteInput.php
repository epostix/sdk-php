<?php

declare(strict_types=1);

namespace Epostix;

final readonly class ExecuteInput
{
    public function __construct(
        public array $path = [],
        public array $query = [],
        public array|\stdClass|null $body = null,
        public ?string $idempotencyKey = null,
        public bool $suppressIdempotencyKey = false,
    ) {}
}
