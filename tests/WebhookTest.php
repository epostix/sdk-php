<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\EpostixWebhookVerificationError;
use Epostix\Tests\Support\FrozenClock;
use Epostix\Webhook\EmailDeliveryEvent;
use Epostix\Webhook\EpostixWebhookVerifier;
use Epostix\Webhook\InboundEmailEvent;
use Epostix\Webhook\UnknownWebhookEvent;
use Epostix\Webhook\WebhookTestEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Epostix\Tests\Support\Fixtures;

final class WebhookTest extends TestCase
{
    public static function vectors(): array
    {
        $fixture = Fixtures::load('webhook');
        $provided = [];

        foreach ($fixture['cases'] as $case) {
            $provided[$case['name']] = [$case, $fixture['now'], $fixture['toleranceSeconds']];
        }

        return $provided;
    }

    #[DataProvider('vectors')]
    public function testTheSharedVerificationVectors(array $case, int $now, int $tolerance): void
    {
        $verifier = new EpostixWebhookVerifier($case['secrets'], $tolerance, new FrozenClock($now));

        $headers = [
            'Webhook-Timestamp' => (string) ($case['headerTimestamp'] ?? $case['ts']),
            'Webhook-Id' => $case['deliveryId'] ?? 'whl_1',
        ];

        if ($case['signature'] !== null) {
            $headers['Webhook-Signature'] = $case['signature'];
        }

        if ($case['expect']['valid'] !== true) {
            try {
                $verifier->verifyAndDecode($case['payload'], $headers);
                self::fail('expected a verification error');
            } catch (EpostixWebhookVerificationError $failure) {
                self::assertSame($case['expect']['reason'], $failure->reason->value);
            }

            return;
        }

        $verified = $verifier->verifyAndDecode($case['payload'], $headers);

        if (isset($case['expect']['eventType'])) {
            self::assertSame($case['expect']['eventType'], $verified->event->type);
        }

        if (isset($case['expect']['eventId'])) {
            self::assertSame($case['expect']['eventId'], $verified->event->id);
        }

        if (isset($case['expect']['deliveryId'])) {
            self::assertSame($case['expect']['deliveryId'], $verified->delivery->deliveryId);
        }

        if (array_key_exists('matchedSecretIndex', $case['expect'])) {
            self::assertSame($case['expect']['matchedSecretIndex'], $verified->delivery->matchedSecretIndex);
        }

        match ($case['expect']['variant'] ?? null) {
            'EmailDeliveryEvent' => self::assertInstanceOf(EmailDeliveryEvent::class, $verified->event),
            'InboundEmailEvent' => self::assertInstanceOf(InboundEmailEvent::class, $verified->event),
            'WebhookTestEvent' => self::assertInstanceOf(WebhookTestEvent::class, $verified->event),
            'UnknownWebhookEvent' => self::assertInstanceOf(UnknownWebhookEvent::class, $verified->event),
            default => self::assertNotNull($verified->event),
        };
    }

    public function testTheSignedEventIdIsTheDeduplicationKeyNotTheDeliveryId(): void
    {
        $fixture = Fixtures::load('webhook');
        $case = null;

        foreach ($fixture['cases'] as $candidate) {
            if ($candidate['name'] === 'delivery_id_is_not_signed_and_not_the_dedup_key') {
                $case = $candidate;
            }
        }

        self::assertNotNull($case);

        $verifier = new EpostixWebhookVerifier($case['secrets'], 300, new FrozenClock($fixture['now']));

        $verified = $verifier->verifyAndDecode($case['payload'], [
            'Webhook-Timestamp' => (string) $case['ts'],
            'Webhook-Signature' => $case['signature'],
            'Webhook-Id' => 'whl_999',
        ]);

        self::assertSame('evt_1', $verified->event->id);
        self::assertSame('whl_999', $verified->delivery->deliveryId);
        self::assertNotSame($verified->event->id, $verified->delivery->deliveryId);
    }

    public function testTheSecretPrefixIsNotStripped(): void
    {
        $payload = '{"id":"evt_1"}';

        self::assertNotSame(
            EpostixWebhookVerifier::sign($payload, 'whsec_abc', 1),
            EpostixWebhookVerifier::sign($payload, 'abc', 1),
        );
    }

    public function testEveryV1PairIsCheckedSoTheSecretCountDoesNotLeak(): void
    {
        $fixture = Fixtures::load('webhook');
        $case = null;

        foreach ($fixture['cases'] as $candidate) {
            if ($candidate['name'] === 'multiple_v1_values_during_rotation') {
                $case = $candidate;
            }
        }

        self::assertNotNull($case);

        $verifier = new EpostixWebhookVerifier($case['secrets'], 300, new FrozenClock($fixture['now']));

        $delivery = $verifier->verify($case['payload'], [
            'Webhook-Timestamp' => (string) $case['ts'],
            'Webhook-Signature' => $case['signature'],
        ]);

        self::assertSame(2, $delivery->signatureCount);
    }

    public function testAnEventCanBeDecodedWithoutVerifying(): void
    {
        $event = EpostixWebhookVerifier::decodeWithoutVerifying(
            '{"id":"evt_1","type":"email.delivered","created_at":"2026-09-06T10:00:00Z","data":{"email_id":"eml_1"}}',
        );

        self::assertInstanceOf(EmailDeliveryEvent::class, $event);
        self::assertSame('eml_1', $event->emailId);
        self::assertTrue($event->isKnownType());
    }
}
