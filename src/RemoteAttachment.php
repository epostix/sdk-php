<?php

declare(strict_types=1);

namespace Epostix;

final class RemoteAttachment extends Attachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $content_url,
        public readonly ?string $content_type = null,
    ) {}

    public static function fromWire(array $wire): self
    {
        return new self(
            Wire::asString($wire['filename'] ?? ''),
            Wire::asString($wire['content_url'] ?? ''),
            array_key_exists('content_type', $wire) && $wire['content_type'] !== null
                ? Wire::asString($wire['content_type'])
                : null,
        );
    }

    public function toWire(): array
    {
        $wire = ['filename' => $this->filename, 'content_url' => $this->content_url];

        if ($this->content_type !== null) {
            $wire['content_type'] = $this->content_type;
        }

        return $wire;
    }
}
