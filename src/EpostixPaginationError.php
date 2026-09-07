<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixPaginationError extends EpostixError
{
    public function __construct(
        public readonly PaginationFailureReason $reason,
        public readonly string $operation,
        string $message,
    ) {
        parent::__construct($message);
    }
}
