<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\EpostixCancelledError;
use Epostix\EpostixConfigurationError;
use Epostix\EpostixOutcomeUnknownError;
use Epostix\EpostixTransportError;
use Epostix\ExecuteInput;
use Epostix\Generated\EmailCreate;
use Epostix\Generated\Operations;
use Epostix\KeyEnvironment;
use Epostix\Mailbox;
use Epostix\RequestOptions;
use Epostix\RetriesExhaustedReason;
use Epostix\RetryPolicy;
use Epostix\Tests\Support\ClientBuilder;
use Epostix\Tests\Support\NetworkFailure;
use Epostix\Tests\Support\RecordingSleeper;
use Epostix\Tests\Support\StubHttpClient;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    private const IDEMPOTENT_OPERATIONS = [
        'sendEmail',
        'sendEmailBatch',
        'createTemplate',
        'createTemplateVersion',
        'createBroadcast',
        'createSuppression',
        'bulkCreateSuppressions',
    ];

    public function testExactlySevenOperationsAreDeduplicatedByTheServer(): void
    {
        $idempotent = [];

        foreach (Operations::all() as $id => $spec) {
            if ($spec->idempotent) {
                $idempotent[] = $id;
            }
        }

        sort($idempotent);
        $expected = self::IDEMPOTENT_OPERATIONS;
        sort($expected);

        self::assertSame($expected, $idempotent);
    }

    public function testAnAutomaticKeyIsMintedForADeduplicatedOperation(): void
    {
        $http = new StubHttpClient([StubHttpClient::json(202, ['id' => 'eml_1'])]);
        $client = ClientBuilder::with($http);

        $client->emails->sendEmail($this->body());

        self::assertMatchesRegularExpression(
            '/^epx_auto_[0-9a-f]{32}$/',
            $http->requests[0]->getHeaderLine('Idempotency-Key'),
        );
    }

    public function testNoKeyIsSentForAnOperationTheServerDoesNotDeduplicate(): void
    {
        $http = new StubHttpClient([StubHttpClient::json(200, ['id' => 'eml_1'])]);
        $client = ClientBuilder::with($http);

        $client->emails->cancelEmail('eml_1');

        self::assertFalse($http->requests[0]->hasHeader('Idempotency-Key'));
    }

    public function testAKeyIsRefusedForAnOperationTheServerDoesNotDeduplicate(): void
    {
        $client = ClientBuilder::with(new StubHttpClient([StubHttpClient::json(200, [])]));

        $this->expectException(EpostixConfigurationError::class);

        $client->transport()->execute(
            Operations::get('cancelEmail'),
            new ExecuteInput(path: ['eml_1'], idempotencyKey: 'k'),
        );
    }

    public function testAnOverlongKeyIsRefused(): void
    {
        $client = ClientBuilder::with(new StubHttpClient([StubHttpClient::json(202, [])]));

        $this->expectException(EpostixConfigurationError::class);

        $client->emails->sendEmail($this->body(), str_repeat('k', 256));
    }

    public function testTheBodyIsSerializedOnceAndReplayedByteIdentically(): void
    {
        $http = new StubHttpClient([
            new NetworkFailure(new Request('POST', 'https://api.example.test/v1/emails')),
            StubHttpClient::json(202, ['id' => 'eml_1']),
        ]);

        $client = ClientBuilder::with($http, RetryPolicy::default(), new RecordingSleeper());
        $client->emails->sendEmail($this->body());

        self::assertCount(2, $http->requests);
        self::assertSame(
            (string) $http->requests[0]->getBody(),
            (string) $http->requests[1]->getBody(),
        );
        self::assertSame(
            $http->requests[0]->getHeaderLine('Idempotency-Key'),
            $http->requests[1]->getHeaderLine('Idempotency-Key'),
        );
    }

    public function testALostResponseOnAMutationReportsAnUnknownOutcome(): void
    {
        $http = new StubHttpClient([new NetworkFailure(new Request('POST', 'https://api.example.test/v1/emails'))]);
        $client = ClientBuilder::with($http);

        try {
            $client->emails->sendEmail($this->body());
            self::fail('expected an unknown outcome');
        } catch (EpostixOutcomeUnknownError $failure) {
            self::assertSame('sendEmail', $failure->operation);
            self::assertTrue($failure->replayableWithKey);
            self::assertNotNull($failure->idempotencyKey);
        }
    }

    public function testALostResponseOnAReadIsAnOrdinaryTransportFailure(): void
    {
        $http = new StubHttpClient([new NetworkFailure(new Request('GET', 'https://api.example.test/v1/emails/e1'))]);
        $client = ClientBuilder::with($http);

        $this->expectException(EpostixTransportError::class);

        $client->emails->getEmail('e1');
    }

    public function testTheDailyQuotaNeverSleeps(): void
    {
        $sleeper = new RecordingSleeper();
        $http = new StubHttpClient([
            StubHttpClient::json(
                429,
                ['status' => 429, 'type' => 'daily_quota_exceeded', 'message' => 'Daily quota reached'],
                ['Retry-After' => '31200'],
            ),
        ]);

        $client = ClientBuilder::with($http, RetryPolicy::default(), $sleeper);

        try {
            $client->emails->sendEmail($this->body());
        } catch (\Epostix\EpostixQuotaExceededError) {
        }

        self::assertSame([], $sleeper->slept);
        self::assertCount(1, $http->requests);
    }

    public function testARateLimitHonorsRetryAfterAndThenSucceeds(): void
    {
        $sleeper = new RecordingSleeper();
        $http = new StubHttpClient([
            StubHttpClient::json(
                429,
                ['status' => 429, 'type' => 'rate_limit_exceeded', 'message' => 'Slow down'],
                ['Retry-After' => '1'],
            ),
            StubHttpClient::json(200, ['id' => 'eml_1', 'status' => 'sent']),
        ]);

        $client = ClientBuilder::with($http, RetryPolicy::default(), $sleeper);
        $email = $client->emails->getEmail('eml_1');

        self::assertSame('eml_1', $email->id);
        self::assertSame([1000.0], $sleeper->slept);
    }

    public function testARetryAfterLongerThanTheDeadlineStopsInsteadOfSleeping(): void
    {
        $sleeper = new RecordingSleeper();
        $http = new StubHttpClient([
            StubHttpClient::json(
                429,
                ['status' => 429, 'type' => 'rate_limit_exceeded', 'message' => 'Slow down'],
                ['Retry-After' => '3600'],
            ),
        ]);

        $client = ClientBuilder::with($http, RetryPolicy::default(), $sleeper);

        try {
            $client->emails->getEmail('eml_1');
            self::fail('expected a rate limit error');
        } catch (\Epostix\EpostixRateLimitError $failure) {
            self::assertSame(
                RetriesExhaustedReason::RETRY_AFTER_EXCEEDS_DEADLINE,
                $failure->responseMetadata->retriesExhaustedReason,
            );
        }

        self::assertSame([], $sleeper->slept);
    }

    public function testAnIdempotencyConflictNeverRetriesAndNeverMintsANewKey(): void
    {
        $http = new StubHttpClient([
            StubHttpClient::json(409, ['status' => 409, 'type' => 'idempotency_conflict', 'message' => 'Different body']),
        ]);

        $client = ClientBuilder::with($http, RetryPolicy::default(), new RecordingSleeper());

        try {
            $client->emails->sendEmail($this->body(), 'my-key');
            self::fail('expected an idempotency conflict');
        } catch (\Epostix\EpostixIdempotencyConflictError $failure) {
            self::assertSame(RetriesExhaustedReason::NOT_RETRYABLE, $failure->responseMetadata->retriesExhaustedReason);
        }

        self::assertCount(1, $http->requests);
        self::assertSame('my-key', $http->requests[0]->getHeaderLine('Idempotency-Key'));
    }

    public function testAnAbortCallbackStopsBeforeTheFirstAttempt(): void
    {
        $http = new StubHttpClient([StubHttpClient::json(200, [])]);
        $client = ClientBuilder::with($http);

        $options = (new RequestOptions())->withAbortCallback(static fn (): bool => true);

        try {
            $client->emails->getEmail('eml_1', $options);
            self::fail('expected a cancellation');
        } catch (EpostixCancelledError) {
        }

        self::assertSame([], $http->requests);
    }

    public function testTheAuthorizationAndUserAgentHeadersAreSet(): void
    {
        $http = new StubHttpClient([StubHttpClient::json(200, ['id' => 'eml_1'])]);
        $client = ClientBuilder::with($http);

        $client->emails->getEmail('eml_1');

        self::assertSame('Bearer tix_test_abc', $http->requests[0]->getHeaderLine('Authorization'));
        self::assertStringStartsWith('epostix-php/', $http->requests[0]->getHeaderLine('User-Agent'));
    }

    public function testAnEmptySuccessReturnsTheResponseMetadata(): void
    {
        $http = new StubHttpClient([new \Nyholm\Psr7\Response(204, ['X-Request-Id' => 'req_9'])]);
        $client = ClientBuilder::with($http);

        $metadata = $client->tags->deleteTag('tag_1');

        self::assertSame(204, $metadata->statusCode);
        self::assertSame('req_9', $metadata->requestId);
    }

    public function testAMissingPathParameterIsRefusedBeforeAnyRequest(): void
    {
        $http = new StubHttpClient([StubHttpClient::json(200, [])]);
        $client = ClientBuilder::with($http);

        $this->expectException(EpostixConfigurationError::class);

        $client->emails->getEmail('');
    }

    public function testTheKeyEnvironmentIsDerivedFromThePrefix(): void
    {
        self::assertSame(KeyEnvironment::Test, KeyEnvironment::of('tix_test_abc'));
        self::assertSame(KeyEnvironment::Live, KeyEnvironment::of('tix_live_abc'));
        self::assertSame(KeyEnvironment::Unrecognized, KeyEnvironment::of('nope'));
    }

    public function testAnApiKeyIsRequired(): void
    {
        $client = new \Epostix\Client('', new \Epostix\ClientOptions(
            httpClient: new StubHttpClient([StubHttpClient::json(200, [])]),
        ));

        $this->expectException(EpostixConfigurationError::class);

        $client->emails->getEmail('eml_1');
    }

    public function testTheBearerPrefixIsRefused(): void
    {
        $client = new \Epostix\Client('Bearer tix_live_abc', new \Epostix\ClientOptions(
            httpClient: new StubHttpClient([StubHttpClient::json(200, [])]),
        ));

        $this->expectException(EpostixConfigurationError::class);

        $client->emails->getEmail('eml_1');
    }

    public function testABodyThatCannotBeRewoundStopsTheRetryRatherThanRiskADuplicate(): void
    {
        $http = new StubHttpClient([
            new NetworkFailure(new Request('POST', 'https://api.example.test/v1/emails')),
            StubHttpClient::json(202, ['id' => 'eml_1']),
        ]);

        $client = new \Epostix\Client('tix_test_abc', new \Epostix\ClientOptions(
            baseUrl: 'https://api.example.test/v1',
            retryPolicy: RetryPolicy::default(),
            httpClient: $http,
            streamFactory: new \Epostix\Tests\Support\UnseekableStreamFactory(),
            clock: new \Epostix\Tests\Support\FrozenClock(),
            random: new \Epostix\Tests\Support\FixedRandomSource(),
            sleeper: new RecordingSleeper(),
        ));

        try {
            $client->emails->sendEmail($this->body());
            self::fail('expected an unknown outcome');
        } catch (EpostixOutcomeUnknownError $failure) {
            self::assertSame(
                RetriesExhaustedReason::BODY_NOT_REPLAYABLE,
                $failure->responseMetadata->retriesExhaustedReason,
            );
        }

        self::assertCount(1, $http->requests);
    }

    private function body(): EmailCreate
    {
        return new EmailCreate(
            from: Mailbox::address('billing@mailer.example.com'),
            subject: 'Your receipt',
            to: 'rae@example.com',
            text: 'Confirmed.',
        );
    }
}
