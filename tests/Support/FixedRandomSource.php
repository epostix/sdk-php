<?php

declare(strict_types=1);

namespace Epostix\Tests\Support;

use Epostix\RandomSource;

final class FixedRandomSource implements RandomSource
{
    public function __construct(private readonly float $value = 0.5, private readonly string $filler = "\x00") {}

    public function fraction(): float
    {
        return $this->value;
    }

    public function bytes(int $length): string
    {
        return str_repeat($this->filler, $length);
    }
}
