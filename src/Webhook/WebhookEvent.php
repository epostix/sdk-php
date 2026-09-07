<?php

declare(strict_types=1);

namespace Epostix\Webhook;

use Epostix\EpostixWebhookVerificationError;
use Epostix\WebhookFailureReason;
use Epostix\Wire;

abstract class WebhookEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?\DateTimeImmutable $createdAt,
        public readonly array $data,
    ) {}

    public function isKnownType(): bool
    {
        return WebhookEventType::isKnown($this->type);
    }

    public static function decode(string $payload): self
    {
        $parsed = json_decode($payload, true);

        if (!is_array($parsed)) {
            throw new EpostixWebhookVerificationError(
                WebhookFailureReason::MalformedPayload,
                'the payload is not valid JSON',
            );
        }

        $id = Wire::asString($parsed['id'] ?? '');
        $type = Wire::asString($parsed['type'] ?? '');
        $data = Wire::asArray($parsed['data'] ?? []);

        $createdAt = null;

        if (isset($parsed['created_at']) && is_string($parsed['created_at'])) {
            $createdAt = Wire::decodeDateTime($parsed['created_at']);
        }

        if (in_array($type, WebhookEventType::DELIVERY_TYPES, true)) {
            return new EmailDeliveryEvent($id, $type, $createdAt, $data, Wire::asString($data['email_id'] ?? ''));
        }

        if ($type === WebhookEventType::EMAIL_RECEIVED) {
            return new InboundEmailEvent($id, $type, $createdAt, $data, Wire::asString($data['inbound_id'] ?? ''));
        }

        if ($type === WebhookEventType::WEBHOOK_TEST) {
            return new WebhookTestEvent($id, $type, $createdAt, $data, Wire::asString($data['message'] ?? ''));
        }

        return new UnknownWebhookEvent($id, $type, $createdAt, $data, $payload);
    }
}
