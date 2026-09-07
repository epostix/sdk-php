<?php

declare(strict_types=1);

namespace Epostix;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Transport
{
    public const IDEMPOTENCY_KEY_MAXIMUM_LENGTH = 255;
    public const IDEMPOTENCY_RETENTION_HOURS = 24;

    private const RAW_BODY_SNIPPET_LIMIT = 8192;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly RetryPolicy $retryPolicy,
        private readonly array $defaultHeaders,
        private readonly string $userAgent,
        private readonly bool $automaticIdempotencyKeys,
        private readonly Clock $clock,
        private readonly RandomSource $random,
        private readonly Sleeper $sleeper,
        private readonly ?EpostixConfigurationError $configurationError = null,
    ) {}

    public function generateIdempotencyKey(): string
    {
        return 'epx_auto_' . bin2hex($this->random->bytes(16));
    }

    public function execute(
        OperationSpec $spec,
        ExecuteInput $input,
        ?RequestOptions $options = null,
    ): DecodedResponse {
        if ($this->configurationError !== null) {
            throw $this->configurationError;
        }

        $policy = $options?->retryPolicy ?? $this->retryPolicy;
        $deadline = $options?->operationDeadlineMilliseconds ?? $policy->operationDeadlineMilliseconds;
        $started = $this->clock->monotonicMilliseconds();

        $idempotencyKey = $this->resolveIdempotencyKey($spec, $input);
        $body = $input->body === null ? null : Wire::encodeBody($input->body);
        $url = ($options?->baseUrl ?? $this->baseUrl)
            . $this->buildPath($spec, $input->path)
            . $this->buildQuery($input->query);

        $attemptRecords = [];
        $attemptNumber = 0;
        $sleptBefore = 0.0;
        $bytesWereWritten = false;
        $bodyIsReplayable = true;
        $lastError = null;
        $exhausted = null;

        while (true) {
            $attemptNumber += 1;
            $this->guardAbort($options);

            $attemptStart = $this->clock->monotonicMilliseconds();
            $remaining = $deadline - ($attemptStart - $started);

            if ($remaining <= 0.0) {
                $exhausted = RetriesExhaustedReason::DEADLINE_EXHAUSTED;
                $lastError ??= new EpostixTimeoutError(TimeoutPhase::OperationDeadline);
                break;
            }

            $request = $this->buildRequest($spec, $url, $body, $idempotencyKey, $options);
            $bodyIsReplayable = $body === null || $request->getBody()->isSeekable();

            $response = null;
            $failure = null;

            try {
                $response = $this->httpClient->sendRequest($request);
                $bytesWereWritten = true;
            } catch (ClientExceptionInterface $caught) {
                $failure = $caught;

                if ($body !== null) {
                    $bytesWereWritten = true;
                }
            }

            $elapsed = $this->clock->monotonicMilliseconds() - $attemptStart;

            if ($response !== null) {
                $metadata = $this->metadataOf(
                    $response,
                    $attemptNumber,
                    $this->clock->monotonicMilliseconds() - $started,
                    $idempotencyKey,
                );

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $attemptRecords[] = new AttemptRecord(
                        $attemptNumber,
                        $attemptStart,
                        $elapsed,
                        $response->getStatusCode(),
                        null,
                        $metadata->retryAfterSeconds,
                        $sleptBefore,
                    );

                    $metadata->attempts = $attemptNumber;
                    $metadata->attemptRecords = $attemptRecords;

                    return new DecodedResponse($this->decodeSuccess($spec, $response, $metadata), $metadata);
                }

                $lastError = $this->errorOf($response, $metadata);
                $attemptRecords[] = new AttemptRecord(
                    $attemptNumber,
                    $attemptStart,
                    $elapsed,
                    $response->getStatusCode(),
                    $lastError->type,
                    $metadata->retryAfterSeconds,
                    $sleptBefore,
                );
            } else {
                $lastError = new EpostixTransportError('the request could not be completed', $failure);
                $attemptRecords[] = new AttemptRecord(
                    $attemptNumber,
                    $attemptStart,
                    $elapsed,
                    null,
                    null,
                    null,
                    $sleptBefore,
                );
            }

            $decision = Retry::classify(new ClassifyContext(
                $policy,
                $spec->retryClass,
                $attemptNumber,
                $deadline - ($this->clock->monotonicMilliseconds() - $started),
                $lastError,
                $idempotencyKey !== null,
                $bodyIsReplayable,
                $bytesWereWritten,
                false,
                $this->random,
            ));

            if (!$decision->shouldRetry) {
                $exhausted = $decision->reason;
                break;
            }

            $this->guardAbort($options);

            if ($this->clock->monotonicMilliseconds() - $started + $decision->delayMilliseconds >= $deadline) {
                $exhausted = RetriesExhaustedReason::DEADLINE_EXHAUSTED;
                break;
            }

            $sleptBefore = $decision->delayMilliseconds;
            $this->sleeper->sleep($decision->delayMilliseconds, $options?->abort);
        }

        throw $this->finalError(
            $spec,
            $lastError,
            $exhausted,
            $idempotencyKey,
            $bytesWereWritten,
            $attemptNumber,
            $this->clock->monotonicMilliseconds() - $started,
            $attemptRecords,
        );
    }

    public function sendOnce(
        OperationSpec $spec,
        ExecuteInput $input,
        ?RequestOptions $options = null,
    ): RawResponse {
        if ($this->configurationError !== null) {
            throw $this->configurationError;
        }

        $url = ($options?->baseUrl ?? $this->baseUrl)
            . $this->buildPath($spec, $input->path)
            . $this->buildQuery($input->query);

        $body = $input->body === null ? null : Wire::encodeBody($input->body);
        $request = $this->buildRequest($spec, $url, $body, $input->idempotencyKey, $options);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $caught) {
            throw new EpostixTransportError('the request could not be completed', $caught);
        }

        $metadata = $this->metadataOf($response, 1, 0.0, $input->idempotencyKey);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw $this->errorOf($response, $metadata);
        }

        return new RawResponse($response, $metadata);
    }

    private function guardAbort(?RequestOptions $options): void
    {
        if ($options?->abort !== null && ($options->abort)() === true) {
            throw new EpostixCancelledError('the operation was cancelled');
        }
    }

    private function buildRequest(
        OperationSpec $spec,
        string $url,
        ?string $body,
        ?string $idempotencyKey,
        ?RequestOptions $options,
    ): RequestInterface {
        $request = $this->requestFactory->createRequest($spec->method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('User-Agent', $this->userAgent);

        foreach ($this->defaultHeaders as $name => $value) {
            $request = $request->withHeader((string) $name, (string) $value);
        }

        foreach ($options?->headers ?? [] as $name => $value) {
            $request = $request->withHeader((string) $name, (string) $value);
        }

        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($body));
        }

        return $request;
    }

    private function resolveIdempotencyKey(OperationSpec $spec, ExecuteInput $input): ?string
    {
        if ($input->idempotencyKey !== null) {
            if (!$spec->idempotent) {
                throw new EpostixConfigurationError(
                    $spec->id . ' does not support an idempotency key:'
                        . ' the server does not deduplicate this operation',
                );
            }

            if (strlen($input->idempotencyKey) > self::IDEMPOTENCY_KEY_MAXIMUM_LENGTH) {
                throw new EpostixConfigurationError(
                    'idempotency key must be ' . self::IDEMPOTENCY_KEY_MAXIMUM_LENGTH . ' characters or fewer',
                );
            }

            return $input->idempotencyKey;
        }

        if ($input->suppressIdempotencyKey || !$spec->idempotent || !$this->automaticIdempotencyKeys) {
            return null;
        }

        return $this->generateIdempotencyKey();
    }

    private function buildPath(OperationSpec $spec, array $values): string
    {
        $path = '';

        foreach ($spec->pathLiterals as $index => $literal) {
            $path .= $literal;

            if ($index >= count($spec->pathParams)) {
                continue;
            }

            $value = $values[$index] ?? '';

            if ($value === '') {
                throw new EpostixConfigurationError(
                    $spec->id . ': path parameter "' . $spec->pathParams[$index] . '" is required',
                );
            }

            $path .= rawurlencode($value);
        }

        return $path;
    }

    private function buildQuery(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($this->queryValue($item));
                }

                continue;
            }

            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($this->queryValue($value));
        }

        return $parts === [] ? '' : '?' . implode('&', $parts);
    }

    private function queryValue(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return Wire::encodeDateTime($value);
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return Wire::asString($value);
    }

    private function metadataOf(
        ResponseInterface $response,
        int $attemptNumber,
        float $elapsed,
        ?string $idempotencyKey,
    ): ResponseMetadata {
        $requestId = $response->getHeaderLine('X-Request-Id');

        if ($requestId === '') {
            $requestId = $response->getHeaderLine('Request-Id');
        }

        return new ResponseMetadata(
            $response->getStatusCode(),
            $requestId,
            Headers::rateLimit($response),
            Headers::retryAfterSeconds($response),
            $response->getHeaderLine('Idempotent-Replayed') === 'true',
            $idempotencyKey,
            $attemptNumber,
            $elapsed,
        );
    }

    private function decodeSuccess(OperationSpec $spec, ResponseInterface $response, ResponseMetadata $metadata): mixed
    {
        if ($spec->empty) {
            return null;
        }

        $text = (string) $response->getBody();

        if ($text === '') {
            return null;
        }

        $decoded = json_decode($text, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new EpostixTransportError(
                'the response body could not be decoded: ' . json_last_error_msg(),
                null,
                $metadata,
            );
        }

        return $decoded;
    }

    private function errorOf(ResponseInterface $response, ResponseMetadata $metadata): EpostixApiError
    {
        $text = (string) $response->getBody();
        $parsed = json_decode($text, true);

        if (!is_array($parsed) || !isset($parsed['type']) || !is_string($parsed['type'])) {
            $snippet = substr($text, 0, self::RAW_BODY_SNIPPET_LIMIT);

            return EpostixApiError::build(
                $response->getStatusCode(),
                '',
                $response->getReasonPhrase() !== ''
                    ? $response->getReasonPhrase()
                    : 'the server returned a non-JSON error response',
                $metadata->requestId,
                null,
                [],
                $metadata,
                $snippet,
                strlen($text) > strlen($snippet),
            );
        }

        $type = $parsed['type'];

        if ($type === EpostixErrorType::MONTHLY_QUOTA_EXCEEDED) {
            $metadata->retryAfterSeconds = null;
        }

        $details = [];

        foreach (Wire::asList($parsed['details'] ?? []) as $entry) {
            if (is_array($entry)) {
                $details[] = \Epostix\Generated\APIErrorDetail::fromWire($entry);
            }
        }

        return EpostixApiError::build(
            is_int($parsed['status'] ?? null) ? $parsed['status'] : $response->getStatusCode(),
            $type,
            is_string($parsed['message'] ?? null) ? $parsed['message'] : '',
            is_string($parsed['request_id'] ?? null) ? $parsed['request_id'] : $metadata->requestId,
            is_string($parsed['doc_url'] ?? null) ? $parsed['doc_url'] : null,
            $details,
            $metadata,
        );
    }

    private function finalError(
        OperationSpec $spec,
        ?\Throwable $lastError,
        ?string $exhausted,
        ?string $idempotencyKey,
        bool $bytesWereWritten,
        int $attempts,
        float $elapsed,
        array $attemptRecords,
    ): \Throwable {
        if ($lastError instanceof EpostixApiError) {
            $lastError->responseMetadata->retriesExhaustedReason = $exhausted;
            $lastError->responseMetadata->attempts = $attempts;
            $lastError->responseMetadata->attemptRecords = $attemptRecords;

            return $lastError;
        }

        if ($spec->method !== 'GET' && $bytesWereWritten) {
            return new EpostixOutcomeUnknownError(
                $spec->id,
                $idempotencyKey,
                $idempotencyKey !== null && $spec->retryClass === RetryClass::DeduplicatedAdmission,
                new ResponseMetadata(
                    idempotencyKey: $idempotencyKey,
                    attempts: $attempts,
                    elapsedMilliseconds: $elapsed,
                    attemptRecords: $attemptRecords,
                    retriesExhaustedReason: $exhausted,
                ),
                $lastError,
            );
        }

        return $lastError ?? new EpostixTransportError('the request could not be completed');
    }
}
