<?php

declare(strict_types=1);

namespace Epostix\Webhook;

use Epostix\Clock;
use Epostix\EpostixWebhookVerificationError;
use Epostix\SystemClock;
use Epostix\WebhookFailureReason;
use Psr\Http\Message\ServerRequestInterface;

final class EpostixWebhookVerifier
{
    public const HEADER_ID = 'Webhook-Id';
    public const HEADER_TIMESTAMP = 'Webhook-Timestamp';
    public const HEADER_SIGNATURE = 'Webhook-Signature';
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    private readonly array $secrets;

    public function __construct(
        array $secrets,
        private readonly int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
        private readonly ?Clock $clock = null,
    ) {
        if ($secrets === []) {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::NoMatchingSignature,
                'at least one signing secret is required',
            );
        }

        $this->secrets = array_values($secrets);
    }

    public static function fromRotation(string $currentSecret, string $previousSecret): self
    {
        return new self([$currentSecret, $previousSecret]);
    }

    public static function sign(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    public static function decodeWithoutVerifying(string $payload): WebhookEvent
    {
        return WebhookEvent::decode($payload);
    }

    public function verify(string $payload, ServerRequestInterface|array $headers): WebhookDelivery
    {
        $signatureHeader = self::header($headers, self::HEADER_SIGNATURE);

        if ($signatureHeader === null || $signatureHeader === '') {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::MissingSignatureHeader,
                self::HEADER_SIGNATURE . ' is missing',
            );
        }

        $timestampHeader = self::header($headers, self::HEADER_TIMESTAMP);

        if ($timestampHeader === null || $timestampHeader === '') {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::MissingTimestampHeader,
                self::HEADER_TIMESTAMP . ' is missing',
            );
        }

        $parsed = self::parseSignatureHeader($signatureHeader);

        if ($parsed === null) {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::MalformedSignatureHeader,
                self::HEADER_SIGNATURE . ' is not in the form t=...,v1=...',
            );
        }

        [$timestamp, $signatures] = $parsed;

        if ((string) $timestamp !== trim($timestampHeader)) {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::MalformedSignatureHeader,
                self::HEADER_TIMESTAMP . ' and the signed timestamp disagree',
            );
        }

        $now = ($this->clock ?? new SystemClock())->now()->getTimestamp();

        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::TimestampOutOfTolerance,
                'the signature timestamp is outside the ' . $this->toleranceSeconds . ' second tolerance',
            );
        }

        $matched = -1;

        foreach ($this->secrets as $index => $secret) {
            $expected = self::sign($payload, $secret, $timestamp);

            foreach ($signatures as $candidate) {
                if (hash_equals($expected, $candidate) && $matched < 0) {
                    $matched = $index;
                }
            }
        }

        if ($matched < 0) {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::NoMatchingSignature,
                'no configured secret produced a matching signature',
            );
        }

        return new WebhookDelivery(
            self::header($headers, self::HEADER_ID) ?? '',
            (new \DateTimeImmutable('@' . $timestamp))->setTimezone(new \DateTimeZone('UTC')),
            $matched,
            count($signatures),
        );
    }

    public function verifyAndDecode(string $payload, ServerRequestInterface|array $headers): VerifiedWebhook
    {
        $delivery = $this->verify($payload, $headers);

        return new VerifiedWebhook(WebhookEvent::decode($payload), $delivery);
    }

    private static function parseSignatureHeader(string $raw): ?array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $raw) as $part) {
            $separator = strpos($part, '=');

            if ($separator === false) {
                return null;
            }

            $key = trim(substr($part, 0, $separator));
            $value = trim(substr($part, $separator + 1));

            if ($key === 't') {
                if (preg_match('/^-?\d+$/', $value) !== 1) {
                    return null;
                }

                $timestamp = (int) $value;

                continue;
            }

            if ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return null;
        }

        return [$timestamp, $signatures];
    }

    private static function header(ServerRequestInterface|array $headers, string $name): ?string
    {
        if ($headers instanceof ServerRequestInterface) {
            $line = $headers->getHeaderLine($name);

            return $line === '' ? null : $line;
        }

        $lower = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $lower) {
                continue;
            }

            if (is_array($value)) {
                return $value === [] ? null : (string) $value[0];
            }

            return (string) $value;
        }

        return null;
    }
}
