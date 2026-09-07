<?php

declare(strict_types=1);

namespace Epostix;

enum KeyEnvironment: string
{
    case Live = 'live';
    case Test = 'test';
    case Unrecognized = 'unrecognized';

    public static function of(string $apiKey): self
    {
        if (str_starts_with($apiKey, 'tix_live_')) {
            return self::Live;
        }

        if (str_starts_with($apiKey, 'tix_test_')) {
            return self::Test;
        }

        return self::Unrecognized;
    }
}
