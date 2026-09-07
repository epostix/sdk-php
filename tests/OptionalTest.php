<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\Optional;
use PHPUnit\Framework\TestCase;

final class OptionalTest extends TestCase
{
    public function testAnExplicitNullIsDistinctFromAValue(): void
    {
        self::assertTrue(Optional::null()->isNull());
        self::assertFalse(Optional::of('x')->isNull());
        self::assertSame('x', Optional::of('x')->value());
    }

    public function testAFalsyValueIsStillAValue(): void
    {
        self::assertFalse(Optional::of(false)->isNull());
        self::assertFalse(Optional::of(false)->value());
        self::assertFalse(Optional::of('')->isNull());
        self::assertSame('', Optional::of('')->value());
        self::assertSame(0, Optional::of(0)->value());
    }

    public function testValueOrFallsBackOnlyForAnExplicitNull(): void
    {
        self::assertSame('fallback', Optional::null()->valueOr('fallback'));
        self::assertSame('', Optional::of('')->valueOr('fallback'));
    }
}
