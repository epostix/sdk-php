<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\AttachmentLimits;
use Epostix\EpostixAttachmentTooLargeError;
use Epostix\Generated\Operations;
use Epostix\Generated\Resource\AttachmentsResource as GeneratedAttachments;
use Epostix\Generated\Resource\InboundResource as GeneratedInbound;
use Epostix\Tests\Support\ClientBuilder;
use Epostix\Tests\Support\StubHttpClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class OverrideTest extends TestCase
{
    public function testTheGeneratorEmitsNoMethodForAnOverriddenOperation(): void
    {
        self::assertFalse(method_exists(GeneratedAttachments::class, 'uploadAttachment'));
        self::assertFalse(method_exists(GeneratedInbound::class, 'getInboundEmailRaw'));
    }

    public function testTheOverriddenOperationsKeepTheirSpecEntry(): void
    {
        self::assertSame('uploadAttachment', Operations::get('uploadAttachment')->id);
        self::assertTrue(Operations::get('getInboundEmailRaw')->binary);
    }

    public function testTheHandWrittenUploadEncodesTheContents(): void
    {
        $http = new StubHttpClient([StubHttpClient::json(201, ['id' => 'att_1', 'filename' => 'r.pdf'])]);
        $client = ClientBuilder::with($http);

        $response = $client->attachments->uploadAttachment('r.pdf', 'hello', 'application/pdf');

        self::assertSame('att_1', $response->id);

        $sent = json_decode((string) $http->requests[0]->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(base64_encode('hello'), $sent['content']);
        self::assertSame('application/pdf', $sent['content_type']);
    }

    public function testBothOverriddenOperationsAreReachableFromTheClient(): void
    {
        $client = ClientBuilder::with(new StubHttpClient([StubHttpClient::json(200, [])]));

        self::assertTrue(method_exists($client->attachments, 'uploadAttachment'));
        self::assertTrue(method_exists($client->inbound, 'getInboundEmailRaw'));
        self::assertInstanceOf(\Epostix\AttachmentsResource::class, $client->attachments);
        self::assertInstanceOf(\Epostix\InboundResource::class, $client->inbound);
    }

    public function testEveryOperationInTheSpecIsReachableFromTheClient(): void
    {
        $resources = [
            $client = ClientBuilder::with(new StubHttpClient([StubHttpClient::json(200, [])])),
        ];

        $reachable = [];
        $queue = [
            $client->analytics, $client->apiKeys, $client->attachments, $client->broadcasts,
            $client->contacts, $client->domains, $client->emails, $client->events,
            $client->inbound, $client->suppressions, $client->tags, $client->templates,
            $client->templates->versions, $client->webhooks, $client->webhooks->logs,
        ];

        foreach ($queue as $resource) {
            foreach (get_class_methods($resource) as $method) {
                $reachable[strtolower($method)] = true;
            }
        }

        $missing = [];

        foreach (array_keys(Operations::all()) as $id) {
            if (!array_key_exists(strtolower($id), $reachable)) {
                $missing[] = $id;
            }
        }

        self::assertSame([], $missing);
        self::assertCount(79, Operations::all());
        self::assertNotSame([], $resources);
    }

    public function testTheUploadProjectsItsSizeBeforeAnyNetworkCall(): void
    {
        $http = new StubHttpClient([StubHttpClient::json(201, ['id' => 'att_1'])]);
        $client = ClientBuilder::with($http);

        $oversize = str_repeat('x', AttachmentLimits::ATTACHMENT_DECODED_LIMIT_BYTES + 1);

        try {
            $client->attachments->uploadAttachment('huge.bin', $oversize, 'application/octet-stream');
            self::fail('expected the attachment to be refused');
        } catch (EpostixAttachmentTooLargeError $failure) {
            self::assertSame('huge.bin', $failure->filename);
        }

        self::assertSame([], $http->requests);
    }

    public function testTheRawMessageIsReturnedAsAStream(): void
    {
        $http = new StubHttpClient([new Response(200, ['Content-Type' => 'message/rfc822'], 'From: a@b.c')]);
        $client = ClientBuilder::with($http);

        $raw = $client->inbound->getInboundEmailRaw('inb_1');

        self::assertSame('message/rfc822', $raw->contentType);
        self::assertSame('From: a@b.c', $raw->contents(1024));
    }
}
