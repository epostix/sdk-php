<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixTimeoutError extends EpostixTransportError
{
    public function __construct(
        public readonly TimeoutPhase $phase,
        ?\Throwable $previous = null,
        ?ResponseMetadata $responseMetadata = null,
    ) {
        parent::__construct('the request timed out during ' . $phase->value, $previous, $responseMetadata);
    }
}
