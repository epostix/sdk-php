<?php

declare(strict_types=1);

namespace Epostix\Webhook;

final readonly class VerifiedWebhook
{
    public function __construct(public WebhookEvent $event, public WebhookDelivery $delivery) {}
}
