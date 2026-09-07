<?php

declare(strict_types=1);

namespace Epostix\Pagination;

use Epostix\ResponseMetadata;

final readonly class Page
{
    public function __construct(
        public array $data,
        public bool $hasMore,
        public ?string $nextCursor,
        public bool $continuationUnavailable,
        public ResponseMetadata $responseMetadata,
    ) {}
}
