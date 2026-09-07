<?php

declare(strict_types=1);

namespace Epostix;

final class Wire
{
    public static function encodeDateTime(\DateTimeInterface $value): string
    {
        $format = ((int) $value->format('u')) === 0 ? 'Y-m-d\TH:i:sP' : 'Y-m-d\TH:i:s.uP';

        return $value->format($format);
    }

    public static function decodeDateTime(mixed $value): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        $text = self::asString($value);

        if ($text === '') {
            return new \DateTimeImmutable('@0');
        }

        try {
            return new \DateTimeImmutable($text);
        } catch (\Exception $failure) {
            throw new EpostixTransportError('a timestamp could not be parsed: ' . $text, $failure);
        }
    }

    public static function recipients(string|array $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return array_values(array_map(static fn ($item): string => self::asString($item), $value));
    }

    public static function mailbox(Mailbox|string $value): string|array
    {
        if (is_string($value)) {
            return $value;
        }

        return $value->narrowest();
    }

    public static function object(array $fields): array|\stdClass
    {
        return $fields === [] ? new \stdClass() : $fields;
    }

    public static function map(array $entries): array|\stdClass
    {
        return $entries === [] ? new \stdClass() : $entries;
    }

    public static function asString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return '';
    }

    public static function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }

    public static function asFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return 0.0;
    }

    public static function asBool(mixed $value): bool
    {
        return $value === true;
    }

    public static function asArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    public static function asList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    public static function asStringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return array_values(array_map(static fn ($item): string => self::asString($item), self::asArray($value)));
    }

    public static function encodeBody(array|\stdClass $body): string
    {
        $payload = $body === [] ? new \stdClass() : $body;

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new EpostixConfigurationError('the request body could not be encoded: ' . json_last_error_msg());
        }

        return $encoded;
    }
}
