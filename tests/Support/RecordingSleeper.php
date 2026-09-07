<?php

declare(strict_types=1);

namespace Epostix\Tests\Support;

use Epostix\Sleeper;

final class RecordingSleeper implements Sleeper
{
    public array $slept = [];

    public function sleep(float $milliseconds, ?\Closure $abort): void
    {
        $this->slept[] = $milliseconds;
    }
}
