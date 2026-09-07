<?php

declare(strict_types=1);

namespace Epostix\Webhook;

final readonly class WebhookDelivery
{
    public function __construct(
        public string $deliveryId,
        public \DateTimeImmutable $timestamp,
        public int $matchedSecretIndex,
        public int $signatureCount,
    ) {}
}
