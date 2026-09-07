<?php

declare(strict_types=1);

namespace Epostix;

final class AttachmentLimits
{
    public const ATTACHMENT_DECODED_LIMIT_BYTES = 41943040;
    public const REQUEST_BODY_LIMIT_BYTES = 52428800;

    public static function projectEncodedSize(int $decodedBytes): int
    {
        return intdiv($decodedBytes + 2, 3) * 4;
    }

    public static function assertWithinLimits(array $sizesByFilename): void
    {
        $total = 0;

        foreach ($sizesByFilename as $filename => $bytes) {
            if ($bytes > self::ATTACHMENT_DECODED_LIMIT_BYTES) {
                throw new EpostixAttachmentTooLargeError(
                    (string) $filename,
                    $bytes,
                    self::ATTACHMENT_DECODED_LIMIT_BYTES,
                );
            }

            $total += $bytes;
        }

        $projected = self::projectEncodedSize($total);

        if ($projected > self::REQUEST_BODY_LIMIT_BYTES) {
            throw new EpostixRequestTooLargeError(
                $projected,
                self::REQUEST_BODY_LIMIT_BYTES,
                count($sizesByFilename),
            );
        }
    }
}
