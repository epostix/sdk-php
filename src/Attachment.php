<?php

declare(strict_types=1);

namespace Epostix;

use Epostix\Generated\AttachmentContentDisposition;
use Psr\Http\Message\StreamInterface;

abstract class Attachment
{
    abstract public function toWire(): array;

    public static function fromWire(array $wire): self
    {
        if (array_key_exists('content', $wire) && array_key_exists('content_type', $wire)) {
            return InlineAttachment::fromWire($wire);
        }

        if (array_key_exists('content_url', $wire)) {
            return RemoteAttachment::fromWire($wire);
        }

        if (array_key_exists('attachment_id', $wire)) {
            return AttachmentReference::fromWire($wire);
        }

        throw new EpostixTransportError(
            'the server returned an attachment shape this version does not recognise',
        );
    }

    public static function fromContents(
        string $filename,
        string $contents,
        string $contentType,
        ?AttachmentContentDisposition $contentDisposition = null,
        ?string $contentId = null,
    ): InlineAttachment {
        AttachmentLimits::assertWithinLimits([$filename => strlen($contents)]);

        return new InlineAttachment(
            $filename,
            base64_encode($contents),
            $contentType,
            $contentDisposition,
            $contentId,
        );
    }

    public static function fromPath(
        string $path,
        string $contentType = 'application/octet-stream',
        ?string $filename = null,
        ?AttachmentContentDisposition $contentDisposition = null,
        ?string $contentId = null,
    ): InlineAttachment {
        $size = @filesize($path);

        if ($size === false) {
            throw new EpostixConfigurationError('the attachment could not be read: ' . $path);
        }

        $name = $filename ?? basename($path);
        AttachmentLimits::assertWithinLimits([$name => $size]);

        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new EpostixConfigurationError('the attachment could not be read: ' . $path);
        }

        return self::fromContents($name, $contents, $contentType, $contentDisposition, $contentId);
    }

    public static function fromStream(
        string $filename,
        mixed $stream,
        string $contentType = 'application/octet-stream',
        ?AttachmentContentDisposition $contentDisposition = null,
        ?string $contentId = null,
    ): InlineAttachment {
        $limit = AttachmentLimits::ATTACHMENT_DECODED_LIMIT_BYTES;

        if ($stream instanceof StreamInterface) {
            $contents = $stream->read($limit + 1);
        } elseif (is_resource($stream)) {
            $read = stream_get_contents($stream, $limit + 1);
            $contents = $read === false ? '' : $read;
        } else {
            throw new EpostixConfigurationError('fromStream requires a stream resource or a PSR-7 stream');
        }

        if (strlen($contents) > $limit) {
            throw new EpostixAttachmentTooLargeError($filename, strlen($contents), $limit);
        }

        return self::fromContents($filename, $contents, $contentType, $contentDisposition, $contentId);
    }

    public static function fromUploadedId(string $attachmentId): AttachmentReference
    {
        return new AttachmentReference($attachmentId);
    }

    public static function fromRemoteUrl(
        string $filename,
        string $contentUrl,
        ?string $contentType = null,
    ): RemoteAttachment {
        return new RemoteAttachment($filename, $contentUrl, $contentType);
    }
}
