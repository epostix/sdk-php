<?php

declare(strict_types=1);

namespace Epostix;

use Epostix\Generated\AttachmentContentDisposition;

final class InlineAttachment extends Attachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $content,
        public readonly string $content_type,
        public readonly ?AttachmentContentDisposition $content_disposition = null,
        public readonly ?string $content_id = null,
    ) {}

    public static function fromWire(array $wire): self
    {
        $disposition = array_key_exists('content_disposition', $wire) && $wire['content_disposition'] !== null
            ? AttachmentContentDisposition::tryFrom(Wire::asString($wire['content_disposition']))
            : null;

        return new self(
            Wire::asString($wire['filename'] ?? ''),
            Wire::asString($wire['content'] ?? ''),
            Wire::asString($wire['content_type'] ?? ''),
            $disposition,
            array_key_exists('content_id', $wire) && $wire['content_id'] !== null
                ? Wire::asString($wire['content_id'])
                : null,
        );
    }

    public function toWire(): array
    {
        $wire = [
            'filename' => $this->filename,
            'content' => $this->content,
            'content_type' => $this->content_type,
        ];

        if ($this->content_disposition !== null) {
            $wire['content_disposition'] = $this->content_disposition->value;
        }

        if ($this->content_id !== null) {
            $wire['content_id'] = $this->content_id;
        }

        return $wire;
    }
}
