<?php

declare(strict_types=1);

namespace Epostix;

final readonly class Mailbox
{
    private function __construct(public string $email, public ?string $name = null) {}

    public static function address(string $email): self
    {
        return new self($email);
    }

    public static function named(string $name, string $email): self
    {
        return new self($email, $name);
    }

    public static function fromWire(mixed $wire): self
    {
        if (is_string($wire)) {
            return new self($wire);
        }

        if (!is_array($wire)) {
            throw new EpostixTransportError('a mailbox must be a string or an object');
        }

        $name = array_key_exists('name', $wire) && $wire['name'] !== null
            ? Wire::asString($wire['name'])
            : null;

        return new self(Wire::asString($wire['email'] ?? ''), $name);
    }

    public function toWire(): array
    {
        $wire = ['email' => $this->email];

        if ($this->name !== null && $this->name !== '') {
            $wire['name'] = $this->name;
        }

        return $wire;
    }

    public function narrowest(): string|array
    {
        if ($this->name === null || $this->name === '') {
            return $this->email;
        }

        return ['email' => $this->email, 'name' => $this->name];
    }

    public function __toString(): string
    {
        if ($this->name === null || $this->name === '') {
            return $this->email;
        }

        return $this->name . ' <' . $this->email . '>';
    }
}
