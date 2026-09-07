<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixIdempotencyInProgressError extends EpostixApiError
{
    public function retryAfterSeconds(): ?int
    {
        return $this->responseMetadata->retryAfterSeconds;
    }
}
