<?php

declare(strict_types=1);

namespace Epostix;

final readonly class Optional
{
    private function __construct(private bool $explicitNull, private mixed $value) {}

    public static function of(mixed $value): self
    {
        return new self(false, $value);
    }

    public static function null(): self
    {
        return new self(true, null);
    }

    public function isNull(): bool
    {
        return $this->explicitNull;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function valueOr(mixed $fallback): mixed
    {
        return $this->explicitNull ? $fallback : $this->value;
    }
}
