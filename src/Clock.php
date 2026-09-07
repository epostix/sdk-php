<?php

declare(strict_types=1);

namespace Epostix;

interface Clock
{
    public function monotonicMilliseconds(): float;

    public function now(): \DateTimeImmutable;
}
