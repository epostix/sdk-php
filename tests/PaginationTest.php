<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\EpostixConfigurationError;
use Epostix\EpostixPaginationError;
use Epostix\Generated\Params\ListEmailsParams;
use Epostix\Tests\Support\ClientBuilder;
use Epostix\Tests\Support\Fixtures;
use Epostix\Tests\Support\StubHttpClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    public static function cases(): array
    {
        $provided = [];

        foreach (Fixtures::load('pagination')['cases'] as $case) {
            $provided[$case['name']] = [$case];
        }

        return $provided;
    }

    #[DataProvider('cases')]
    public function testTheSharedCursorCorpus(array $case): void
    {
        self::assertSame('listEmails', $case['operation']);

        $responses = [];

        foreach ($case['pages'] as $page) {
            $responses[] = StubHttpClient::json(200, $page['body']);
        }

        $http = new StubHttpClient($responses);
        $client = ClientBuilder::with($http);

        $paginator = $client->emails->listEmailsPaginator(new ListEmailsParams(
            limit: $case['params']['limit'] ?? null,
            status: $case['params']['status'] ?? null,
        ));

        if (isset($case['expect']['error'])) {
            try {
                iterator_to_array($paginator->iterate());
                self::fail('expected a pagination error');
            } catch (EpostixPaginationError $failure) {
                self::assertSame($case['expect']['error'], $failure->reason->value);
            }

            self::assertCount(
                count($case['pages']),
                $http->requests,
                'a protocol fault must be reported on the request that exposes it',
            );

            return;
        }

        if (isset($case['maximumItems'])) {
            $collected = $paginator->collect($case['maximumItems']);

            self::assertSame($case['expect']['items'], array_map(
                static fn ($email): string => $email->id,
                $collected->items,
            ));
            self::assertSame($case['expect']['truncated'], $collected->truncated);
            self::assertCount($case['expect']['requests'], $http->requests);

            return;
        }

        $identifiers = [];

        foreach ($paginator->iterate() as $email) {
            $identifiers[] = $email->id;

            if (isset($case['breakAfter']) && count($identifiers) >= $case['breakAfter']) {
                break;
            }
        }

        self::assertSame($case['expect']['items'], $identifiers);
        self::assertCount($case['expect']['requests'], $http->requests);

        foreach ($case['pages'] as $index => $page) {
            if (!isset($page['query']) || !isset($http->requests[$index])) {
                continue;
            }

            parse_str($http->requests[$index]->getUri()->getQuery(), $sent);

            $expected = $page['query'];
            ksort($expected);
            ksort($sent);

            self::assertSame($expected, $sent, 'page ' . $index . ' query');
        }
    }

    public function testACursorHandedStraightBackIsCaughtOnTheSecondRequest(): void
    {
        $http = new StubHttpClient([
            StubHttpClient::json(200, ['data' => [['id' => 'e1']], 'has_more' => true, 'next_cursor' => 'c1']),
            StubHttpClient::json(200, ['data' => [['id' => 'e2']], 'has_more' => true, 'next_cursor' => 'c1']),
        ]);

        $client = ClientBuilder::with($http);

        try {
            iterator_to_array($client->emails->listEmailsPaginator()->iterate());
            self::fail('expected a repeated cursor error');
        } catch (EpostixPaginationError $failure) {
            self::assertSame('RepeatedCursor', $failure->reason->value);
            self::assertStringContainsString('the same cursor it was given', $failure->getMessage());
        }

        self::assertCount(2, $http->requests);
    }

    public function testADistinctCursorOnEveryPageIsNotAFault(): void
    {
        $http = new StubHttpClient([
            StubHttpClient::json(200, ['data' => [['id' => 'e1']], 'has_more' => true, 'next_cursor' => 'c1']),
            StubHttpClient::json(200, ['data' => [['id' => 'e2']], 'has_more' => true, 'next_cursor' => 'c2']),
            StubHttpClient::json(200, ['data' => [['id' => 'e3']], 'has_more' => false]),
        ]);

        $client = ClientBuilder::with($http);

        $identifiers = [];

        foreach ($client->emails->listEmailsPaginator()->iterate() as $email) {
            $identifiers[] = $email->id;
        }

        self::assertSame(['e1', 'e2', 'e3'], $identifiers);
        self::assertCount(3, $http->requests);
    }

    public function testAnIteratorRefusesToWalkBackwards(): void
    {
        $client = ClientBuilder::with(new StubHttpClient([
            StubHttpClient::json(200, ['data' => [], 'has_more' => false]),
        ]));

        $this->expectException(EpostixConfigurationError::class);

        $client->emails->listEmailsPaginator(new ListEmailsParams(ending_before: 'e9'));
    }

    public function testCollectRequiresAPositiveCap(): void
    {
        $client = ClientBuilder::with(new StubHttpClient([
            StubHttpClient::json(200, ['data' => [], 'has_more' => false]),
        ]));

        $this->expectException(EpostixConfigurationError::class);

        $client->emails->listEmailsPaginator()->collect(0);
    }

    public function testUnknownFieldsDoNotFailAPage(): void
    {
        $client = ClientBuilder::with(new StubHttpClient([
            StubHttpClient::json(200, [
                'data' => [['id' => 'e1', 'future_field' => 42]],
                'has_more' => false,
                'future_top' => true,
            ]),
        ]));

        $emails = iterator_to_array($client->emails->listEmailsPaginator()->iterate());

        self::assertCount(1, $emails);
        self::assertSame(['future_field' => 42], $emails[0]->unknownFields);
    }

    public function testIteratePagesExposesTheCursorAndMetadata(): void
    {
        $client = ClientBuilder::with(new StubHttpClient([
            StubHttpClient::json(200, ['data' => [['id' => 'e1']], 'has_more' => true, 'next_cursor' => 'e1']),
            StubHttpClient::json(200, ['data' => [['id' => 'e2']], 'has_more' => false]),
        ]));

        $cursors = [];

        foreach ($client->emails->listEmailsPaginator()->iteratePages() as $page) {
            $cursors[] = $page->nextCursor;
            self::assertSame(200, $page->responseMetadata->statusCode);
        }

        self::assertSame(['e1', null], $cursors);
    }
}
