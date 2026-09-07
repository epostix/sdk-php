<?php

declare(strict_types=1);

namespace Epostix;

interface Sleeper
{
    public function sleep(float $milliseconds, ?\Closure $abort): void;
}
