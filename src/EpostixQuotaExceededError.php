<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixQuotaExceededError extends EpostixApiError
{
    public function quotaPeriod(): QuotaPeriod
    {
        return $this->type === EpostixErrorType::DAILY_QUOTA_EXCEEDED
            ? QuotaPeriod::Daily
            : QuotaPeriod::Monthly;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->quotaPeriod() === QuotaPeriod::Daily
            ? $this->responseMetadata->retryAfterSeconds
            : null;
    }

    public function nextAttemptAt(?Clock $clock = null): ?\DateTimeImmutable
    {
        $retryAfter = $this->retryAfterSeconds();

        if ($retryAfter === null) {
            return null;
        }

        return ($clock ?? new SystemClock())->now()->modify('+' . $retryAfter . ' seconds');
    }
}
