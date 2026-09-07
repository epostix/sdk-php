<?php

declare(strict_types=1);

namespace Epostix;

final class SystemRandomSource implements RandomSource
{
    public function fraction(): float
    {
        return random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX;
    }

    public function bytes(int $length): string
    {
        return random_bytes($length);
    }
}
