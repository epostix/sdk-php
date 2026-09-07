<?php

declare(strict_types=1);

namespace Epostix\Batch;

enum UnresolvedReason: string
{
    case NotReported = 'NotReported';
    case StatusFailedWithoutError = 'StatusFailedWithoutError';
}
