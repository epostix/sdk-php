<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixOutcomeUnknownError extends EpostixError
{
    public function __construct(
        public readonly string $operation,
        public readonly ?string $idempotencyKey,
        public readonly bool $replayableWithKey,
        public readonly ResponseMetadata $responseMetadata,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            'the outcome of ' . $operation
                . ' could not be established: the request may or may not have been accepted',
            0,
            $previous,
        );
    }
}
