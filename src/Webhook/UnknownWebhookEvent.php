<?php

declare(strict_types=1);

namespace Epostix\Webhook;

final class UnknownWebhookEvent extends WebhookEvent
{
    public function __construct(
        string $id,
        string $type,
        ?\DateTimeImmutable $createdAt,
        array $data,
        public readonly string $rawBody,
    ) {
        parent::__construct($id, $type, $createdAt, $data);
    }
}
