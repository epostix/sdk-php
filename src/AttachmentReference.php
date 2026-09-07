<?php

declare(strict_types=1);

namespace Epostix;

final class AttachmentReference extends Attachment
{
    public function __construct(public readonly string $attachment_id) {}

    public static function fromWire(array $wire): self
    {
        return new self(Wire::asString($wire['attachment_id'] ?? ''));
    }

    public function toWire(): array
    {
        return ['attachment_id' => $this->attachment_id];
    }
}
