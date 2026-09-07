<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixAttachmentTooLargeError extends EpostixConfigurationError
{
    public function __construct(
        public readonly string $filename,
        public readonly int $actualBytes,
        public readonly int $limitBytes,
    ) {
        parent::__construct(
            'attachment "' . $filename . '" is ' . $actualBytes
                . ' bytes, above the ' . $limitBytes . ' byte decoded limit',
        );
    }
}
