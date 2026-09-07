<?php

declare(strict_types=1);

namespace Epostix;

final readonly class DecodedResponse
{
    public function __construct(public mixed $body, public ResponseMetadata $metadata) {}
}
