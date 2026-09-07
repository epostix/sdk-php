<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\Attachment;
use Epostix\AttachmentReference;
use Epostix\EpostixApiError;
use Epostix\EpostixQuotaExceededError;
use Epostix\EpostixTransportError;
use Epostix\Generated\Email;
use Epostix\Generated\EmailStatus;
use Epostix\RemoteAttachment;
use Epostix\Tests\Support\ClientBuilder;
use Epostix\Tests\Support\Fixtures;
use Epostix\Tests\Support\StubHttpClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecodingTest extends TestCase
{
    public static function cases(): array
    {
        $provided = [];

        foreach (Fixtures::load('decoding')['cases'] as $case) {
            $provided[$case['name']] = [$case];
        }

        return $provided;
    }

    #[DataProvider('cases')]
    public function testTheSharedDecodingCorpus(array $case): void
    {
        match ($case['type']) {
            'Email' => $this->assertEmailCase($case),
            'APIError' => $this->assertApiErrorCase($case),
            'raw' => $this->assertRawCase($case),
            'EmailCreateAttachmentsItem' => $this->assertAttachmentCase($case),
            default => self::fail('unmapped fixture type ' . $case['type']),
        };
    }

    public function testAnUnknownEnumMemberNeverThrows(): void
    {
        $email = Email::fromWire(['id' => 'eml_1', 'status' => 'quantum_delivered']);

        self::assertSame('quantum_delivered', $email->status);
        self::assertFalse(EmailStatus::isKnown($email->status));
        self::assertTrue(EmailStatus::isKnown(EmailStatus::DELIVERED));
    }

    private function assertEmailCase(array $case): void
    {
        if (($case['status'] ?? null) === 200) {
            $client = ClientBuilder::with(new StubHttpClient([
                new Response(200, ['Content-Type' => 'application/json'] + ($case['headers'] ?? []),
                    json_encode($case['wire'], JSON_THROW_ON_ERROR)),
            ]));

            $email = $client->emails->getEmail('eml_1');

            if (array_key_exists('idempotentReplayed', $case['expect'])) {
                self::assertSame(
                    $case['expect']['idempotentReplayed'],
                    $email->responseMetadata?->idempotentReplayed,
                );
            }

            return;
        }

        $email = Email::fromWire($case['wire']);

        if (array_key_exists('status', $case['expect'])) {
            self::assertSame($case['expect']['status'], $email->status);
        }

        if (array_key_exists('statusIsKnown', $case['expect'])) {
            self::assertSame($case['expect']['statusIsKnown'], EmailStatus::isKnown($email->status));
        }

        if (array_key_exists('id', $case['expect'])) {
            self::assertSame($case['expect']['id'], $email->id);
        }

        if (array_key_exists('unknownFields', $case['expect'])) {
            self::assertSame($case['expect']['unknownFields'], $email->unknownFields);
        }
    }

    private function assertApiErrorCase(array $case): void
    {
        $failure = $this->failureFrom(new Response(
            $case['status'],
            ['Content-Type' => 'application/json'] + ($case['headers'] ?? []),
            json_encode($case['wire'], JSON_THROW_ON_ERROR),
        ));

        $this->assertErrorExpectations($case['expect'], $failure);
    }

    private function assertRawCase(array $case): void
    {
        $failure = $this->failureFrom(new Response(
            $case['status'],
            ['Content-Type' => $case['contentType']],
            $case['body'],
        ));

        $this->assertErrorExpectations($case['expect'], $failure);

        if (($case['expect']['rawBodySnippetPresent'] ?? false) === true) {
            self::assertSame($case['body'], $failure->rawBodySnippet);
        }
    }

    private function assertAttachmentCase(array $case): void
    {
        if (($case['expect']['error'] ?? null) === 'protocol') {
            $this->expectException(EpostixTransportError::class);
            Attachment::fromWire($case['wire']);

            return;
        }

        $attachment = Attachment::fromWire($case['wire']);

        match ($case['expect']['variant']) {
            'AttachmentRef' => self::assertInstanceOf(AttachmentReference::class, $attachment),
            'AttachmentURL' => self::assertInstanceOf(RemoteAttachment::class, $attachment),
            default => self::fail('unmapped variant ' . $case['expect']['variant']),
        };

        if (isset($case['expect']['attachment_id'])) {
            self::assertSame($case['expect']['attachment_id'], $attachment->toWire()['attachment_id']);
        }
    }

    private function assertErrorExpectations(array $expect, EpostixApiError $failure): void
    {
        self::assertSame('Epostix\\' . $expect['class'], $failure::class);

        if (array_key_exists('type', $expect)) {
            self::assertSame($expect['type'], $failure->type);
        }

        if (array_key_exists('isKnownType', $expect)) {
            self::assertSame($expect['isKnownType'], $failure->isKnownType);
        }

        if (array_key_exists('requestId', $expect)) {
            self::assertSame($expect['requestId'], $failure->requestId);
        }

        if (array_key_exists('retryable', $expect)) {
            self::assertSame($expect['retryable'], $failure->isRetryable());
        }

        if (array_key_exists('fieldErrors', $expect)) {
            self::assertCount($expect['fieldErrors'], $failure->details);
        }

        if (array_key_exists('retryAfterSeconds', $expect)) {
            $retryAfter = method_exists($failure, 'retryAfterSeconds')
                ? $failure->retryAfterSeconds()
                : $failure->responseMetadata->retryAfterSeconds;

            self::assertSame($expect['retryAfterSeconds'], $retryAfter);
        }

        if (array_key_exists('quotaPeriod', $expect)) {
            self::assertInstanceOf(EpostixQuotaExceededError::class, $failure);
            self::assertSame($expect['quotaPeriod'], $failure->quotaPeriod()->value);
        }

        if (array_key_exists('nextAttemptAt', $expect)) {
            self::assertInstanceOf(EpostixQuotaExceededError::class, $failure);
            self::assertSame($expect['nextAttemptAt'], $failure->nextAttemptAt());
        }

        if (array_key_exists('rateLimit', $expect)) {
            $snapshot = $failure->responseMetadata->rateLimit;

            self::assertNotNull($snapshot);
            self::assertSame($expect['rateLimit']['limit'], $snapshot->limit);
            self::assertSame($expect['rateLimit']['remaining'], $snapshot->remaining);
            self::assertSame($expect['rateLimit']['resetAfterSeconds'], $snapshot->resetAfterSeconds);
            self::assertSame($expect['rateLimit']['policyBurst'], $snapshot->policyBurst);
            self::assertSame($expect['rateLimit']['policyWindowSeconds'], $snapshot->policyWindowSeconds);
            self::assertSame($expect['rateLimit']['scope'], $snapshot->scope);
        }
    }

    private function failureFrom(Response $response): EpostixApiError
    {
        $client = ClientBuilder::with(new StubHttpClient([$response]));

        try {
            $client->emails->getEmail('eml_1');
        } catch (EpostixApiError $failure) {
            return $failure;
        }

        self::fail('expected an API error');
    }
}
