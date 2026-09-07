<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\Attachment;
use Epostix\AttachmentLimits;
use Epostix\EpostixAttachmentTooLargeError;
use Epostix\EpostixRequestTooLargeError;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    public function testTheLimitsMatchTheContract(): void
    {
        self::assertSame(41943040, AttachmentLimits::ATTACHMENT_DECODED_LIMIT_BYTES);
        self::assertSame(52428800, AttachmentLimits::REQUEST_BODY_LIMIT_BYTES);
        self::assertFalse(defined(AttachmentLimits::class . '::INLINE_ATTACHMENT_TOTAL_LIMIT_BYTES'));
    }

    public function testTheEncodedSizeIsProjectedBeforeEncoding(): void
    {
        self::assertSame(0, AttachmentLimits::projectEncodedSize(0));
        self::assertSame(4, AttachmentLimits::projectEncodedSize(1));
        self::assertSame(4, AttachmentLimits::projectEncodedSize(3));
        self::assertSame(8, AttachmentLimits::projectEncodedSize(4));
        self::assertSame(
            strlen(base64_encode(str_repeat('x', 1000))),
            AttachmentLimits::projectEncodedSize(1000),
        );
    }

    public function testASingleOversizeAttachmentIsRejectedBeforeEncoding(): void
    {
        $this->expectException(EpostixAttachmentTooLargeError::class);

        AttachmentLimits::assertWithinLimits(['huge.bin' => AttachmentLimits::ATTACHMENT_DECODED_LIMIT_BYTES + 1]);
    }

    public function testAProjectedRequestOverTheLimitIsRejected(): void
    {
        $this->expectException(EpostixRequestTooLargeError::class);

        AttachmentLimits::assertWithinLimits([
            'a.bin' => 20000000,
            'b.bin' => 20000000,
        ]);
    }

    public function testFromPathReadsTheFileAndNamesIt(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'epostix');
        file_put_contents($path, 'payload');

        $attachment = Attachment::fromPath($path, 'text/plain');

        self::assertSame(basename($path), $attachment->filename);
        self::assertSame(base64_encode('payload'), $attachment->content);

        unlink($path);
    }

    public function testFromStreamReadsAStreamResource(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'streamed');
        rewind($stream);

        $attachment = Attachment::fromStream('r.txt', $stream, 'text/plain');

        self::assertSame(base64_encode('streamed'), $attachment->content);

        fclose($stream);
    }

    public function testARemoteAttachmentIsNamedForTheServerFetch(): void
    {
        self::assertFalse(method_exists(Attachment::class, 'fromUrl'));
        self::assertTrue(method_exists(Attachment::class, 'fromRemoteUrl'));
    }
}
