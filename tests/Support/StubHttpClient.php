<?php

declare(strict_types=1);

namespace Epostix\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class StubHttpClient implements ClientInterface
{
    public array $requests = [];

    private array $responses;

    private int $index = 0;

    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    public static function json(int $status, array|string $body, array $headers = []): Response
    {
        $payload = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);

        return new Response($status, ['Content-Type' => 'application/json'] + $headers, $payload);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        $next = $this->responses[min($this->index, count($this->responses) - 1)];
        $this->index += 1;

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
