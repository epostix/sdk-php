<?php

declare(strict_types=1);

namespace Epostix;

final class SliceSleeper implements Sleeper
{
    public const SLICE_MILLISECONDS = 100.0;

    public function sleep(float $milliseconds, ?\Closure $abort): void
    {
        $remaining = $milliseconds;

        while ($remaining > 0.0) {
            if ($abort !== null && $abort() === true) {
                throw new EpostixCancelledError('the operation was cancelled during backoff');
            }

            $slice = min($remaining, self::SLICE_MILLISECONDS);
            usleep((int) round($slice * 1000.0));
            $remaining -= $slice;

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        if ($abort !== null && $abort() === true) {
            throw new EpostixCancelledError('the operation was cancelled during backoff');
        }
    }
}
