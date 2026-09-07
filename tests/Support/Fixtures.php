<?php

declare(strict_types=1);

namespace Epostix\Tests\Support;

final class Fixtures
{
    public static function load(string $name): array
    {
        $path = __DIR__ . '/../fixtures/' . $name . '.json';
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new \RuntimeException('missing fixture: ' . $path);
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException('fixture is not an object: ' . $path);
        }

        return $decoded;
    }
}
