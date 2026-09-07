<?php

declare(strict_types=1);

namespace Epostix;

final readonly class RequestOptions
{
    public function __construct(
        public ?RetryPolicy $retryPolicy = null,
        public ?float $operationDeadlineMilliseconds = null,
        public array $headers = [],
        public ?string $baseUrl = null,
        public ?\Closure $abort = null,
    ) {}

    public function withAbortCallback(callable $abort): self
    {
        return new self(
            $this->retryPolicy,
            $this->operationDeadlineMilliseconds,
            $this->headers,
            $this->baseUrl,
            \Closure::fromCallable($abort),
        );
    }

    public function withRetryPolicy(RetryPolicy $retryPolicy): self
    {
        return new self(
            $retryPolicy,
            $this->operationDeadlineMilliseconds,
            $this->headers,
            $this->baseUrl,
            $this->abort,
        );
    }

    public function withOperationDeadline(float $milliseconds): self
    {
        return new self($this->retryPolicy, $milliseconds, $this->headers, $this->baseUrl, $this->abort);
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self(
            $this->retryPolicy,
            $this->operationDeadlineMilliseconds,
            $headers,
            $this->baseUrl,
            $this->abort,
        );
    }
}
