<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\EpostixCancelledError;
use Epostix\SliceSleeper;
use PHPUnit\Framework\TestCase;

final class SleeperTest extends TestCase
{
    public function testTheAbortCallbackIsPolledBeforeSleeping(): void
    {
        $this->expectException(EpostixCancelledError::class);

        (new SliceSleeper())->sleep(5000.0, static fn (): bool => true);
    }

    public function testTheAbortCallbackIsPolledBetweenSlices(): void
    {
        $polls = 0;

        try {
            (new SliceSleeper())->sleep(400.0, static function () use (&$polls): bool {
                $polls += 1;

                return $polls > 2;
            });

            self::fail('expected a cancellation');
        } catch (EpostixCancelledError) {
        }

        self::assertSame(3, $polls);
    }

    public function testASleepThatIsNotAbortedCompletes(): void
    {
        $sleeper = new SliceSleeper();
        $started = hrtime(true);

        $sleeper->sleep(120.0, null);

        self::assertGreaterThanOrEqual(100.0, (hrtime(true) - $started) / 1000000.0);
        self::assertSame(100.0, SliceSleeper::SLICE_MILLISECONDS);
    }
}
