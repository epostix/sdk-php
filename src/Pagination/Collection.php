<?php

declare(strict_types=1);

namespace Epostix\Pagination;

final readonly class Collection
{
    public function __construct(
        public array $items,
        public bool $truncated,
        public ?string $lastCursor,
    ) {}
}
