<?php

declare(strict_types=1);

namespace Epostix\Webhook;

final class WebhookTestEvent extends WebhookEvent
{
    public function __construct(
        string $id,
        string $type,
        ?\DateTimeImmutable $createdAt,
        array $data,
        public readonly string $message,
    ) {
        parent::__construct($id, $type, $createdAt, $data);
    }
}
