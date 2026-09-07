<?php

declare(strict_types=1);

namespace Epostix\Tests\Support;

use Epostix\Clock;

final class FrozenClock implements Clock
{
    public float $monotonic = 0.0;

    public function __construct(private readonly int $unixSeconds = 0) {}

    public function monotonicMilliseconds(): float
    {
        return $this->monotonic;
    }

    public function now(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $this->unixSeconds))->setTimezone(new \DateTimeZone('UTC'));
    }
}
