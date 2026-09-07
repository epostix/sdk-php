<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixRequestTooLargeError extends EpostixConfigurationError
{
    public function __construct(
        public readonly int $projectedEncodedBytes,
        public readonly int $limitBytes,
        public readonly int $attachmentCount,
    ) {
        parent::__construct(
            $attachmentCount . ' attachment(s) encode to about ' . $projectedEncodedBytes
                . ' bytes, above the ' . $limitBytes
                . ' byte request limit. Upload them first, or pass a content URL.',
        );
    }
}
