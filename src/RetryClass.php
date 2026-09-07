<?php

declare(strict_types=1);

namespace Epostix;

enum RetryClass: string
{
    case SafeRead = 'SafeRead';
    case NaturallyIdempotent = 'NaturallyIdempotent';
    case DeduplicatedAdmission = 'DeduplicatedAdmission';
    case ExcludedMutation = 'ExcludedMutation';
    case Streaming = 'Streaming';
}
