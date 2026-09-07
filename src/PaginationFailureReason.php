<?php

declare(strict_types=1);

namespace Epostix;

enum PaginationFailureReason: string
{
    case MissingCursor = 'MissingCursor';
    case RepeatedCursor = 'RepeatedCursor';
    case ContinuationUnsupported = 'ContinuationUnsupported';
    case PageLimitExceeded = 'PageLimitExceeded';
}
