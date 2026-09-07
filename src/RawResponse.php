<?php

declare(strict_types=1);

namespace Epostix;

use Psr\Http\Message\ResponseInterface;

final readonly class RawResponse
{
    public function __construct(public ResponseInterface $response, public ResponseMetadata $metadata) {}
}
