<?php

declare(strict_types=1);

namespace Epostix;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class ClientOptions
{
    public function __construct(
        public ?string $baseUrl = null,
        public ?RetryPolicy $retryPolicy = null,
        public ?ClientInterface $httpClient = null,
        public ?RequestFactoryInterface $requestFactory = null,
        public ?StreamFactoryInterface $streamFactory = null,
        public array $defaultHeaders = [],
        public ?string $userAgentSuffix = null,
        public bool $automaticIdempotencyKeys = true,
        public ?Clock $clock = null,
        public ?RandomSource $random = null,
        public ?Sleeper $sleeper = null,
    ) {}
}
