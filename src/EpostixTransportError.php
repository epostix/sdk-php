<?php

declare(strict_types=1);

namespace Epostix;

class EpostixTransportError extends EpostixError
{
    public readonly ResponseMetadata $responseMetadata;

    public function __construct(
        string $message,
        ?\Throwable $previous = null,
        ?ResponseMetadata $responseMetadata = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->responseMetadata = $responseMetadata ?? new ResponseMetadata();
    }
}
