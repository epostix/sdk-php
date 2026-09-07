<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixWebhookVerificationError extends EpostixError
{
    public function __construct(public readonly WebhookFailureReason $reason, string $message)
    {
        parent::__construct($message);
    }
}
