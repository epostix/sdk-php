<?php

declare(strict_types=1);

namespace Epostix\Tests\Support;

use Epostix\Client;
use Epostix\ClientOptions;
use Epostix\RetryPolicy;

final class ClientBuilder
{
    public static function with(
        StubHttpClient $httpClient,
        ?RetryPolicy $policy = null,
        ?RecordingSleeper $sleeper = null,
        ?FrozenClock $clock = null,
    ): Client {
        return new Client('tix_test_abc', new ClientOptions(
            baseUrl: 'https://api.example.test/v1',
            retryPolicy: $policy ?? RetryPolicy::disabled(),
            httpClient: $httpClient,
            clock: $clock ?? new FrozenClock(),
            random: new FixedRandomSource(),
            sleeper: $sleeper ?? new RecordingSleeper(),
        ));
    }
}
