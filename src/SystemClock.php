<?php

declare(strict_types=1);

namespace Epostix;

final class SystemClock implements Clock
{
    public function monotonicMilliseconds(): float
    {
        return hrtime(true) / 1000000.0;
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
