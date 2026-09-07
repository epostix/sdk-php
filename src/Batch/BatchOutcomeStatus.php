<?php

declare(strict_types=1);

namespace Epostix\Batch;

enum BatchOutcomeStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unresolved = 'unresolved';
}
