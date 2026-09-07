<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixRateLimitError extends EpostixApiError
{
    public function retryAfterSeconds(): ?int
    {
        return $this->responseMetadata->retryAfterSeconds;
    }

    public function rateLimit(): ?RateLimitSnapshot
    {
        return $this->responseMetadata->rateLimit;
    }
}
