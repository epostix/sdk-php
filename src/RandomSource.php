<?php

declare(strict_types=1);

namespace Epostix;

interface RandomSource
{
    public function fraction(): float;

    public function bytes(int $length): string;
}
