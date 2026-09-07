<?php

declare(strict_types=1);

namespace Epostix;

enum WebhookFailureReason: string
{
    case MissingSignatureHeader = 'MissingSignatureHeader';
    case MissingTimestampHeader = 'MissingTimestampHeader';
    case MalformedSignatureHeader = 'MalformedSignatureHeader';
    case TimestampOutOfTolerance = 'TimestampOutOfTolerance';
    case NoMatchingSignature = 'NoMatchingSignature';
    case MalformedPayload = 'MalformedPayload';
}
