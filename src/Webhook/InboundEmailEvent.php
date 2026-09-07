<?php

declare(strict_types=1);

namespace Epostix\Webhook;

final class InboundEmailEvent extends WebhookEvent
{
    public function __construct(
        string $id,
        string $type,
        ?\DateTimeImmutable $createdAt,
        array $data,
        public readonly string $inboundId,
    ) {
        parent::__construct($id, $type, $createdAt, $data);
    }
}
