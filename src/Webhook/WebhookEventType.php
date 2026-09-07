<?php

declare(strict_types=1);

namespace Epostix\Webhook;

final class WebhookEventType
{
    public const EMAIL_SENT = 'email.sent';
    public const EMAIL_DELIVERED = 'email.delivered';
    public const EMAIL_BOUNCED = 'email.bounced';
    public const EMAIL_OPENED = 'email.opened';
    public const EMAIL_CLICKED = 'email.clicked';
    public const EMAIL_COMPLAINED = 'email.complained';
    public const EMAIL_FAILED = 'email.failed';
    public const EMAIL_DELAYED = 'email.delayed';
    public const EMAIL_RECEIVED = 'email.received';
    public const WEBHOOK_TEST = 'webhook.test';

    public const DELIVERY_TYPES = [
        self::EMAIL_SENT,
        self::EMAIL_DELIVERED,
        self::EMAIL_BOUNCED,
        self::EMAIL_OPENED,
        self::EMAIL_CLICKED,
        self::EMAIL_COMPLAINED,
        self::EMAIL_FAILED,
        self::EMAIL_DELAYED,
    ];

    public const VALUES = [
        self::EMAIL_SENT,
        self::EMAIL_DELIVERED,
        self::EMAIL_BOUNCED,
        self::EMAIL_OPENED,
        self::EMAIL_CLICKED,
        self::EMAIL_COMPLAINED,
        self::EMAIL_FAILED,
        self::EMAIL_DELAYED,
        self::EMAIL_RECEIVED,
        self::WEBHOOK_TEST,
    ];

    public static function isKnown(string $value): bool
    {
        return in_array($value, self::VALUES, true);
    }
}
