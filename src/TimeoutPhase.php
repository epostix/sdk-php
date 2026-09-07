<?php

declare(strict_types=1);

namespace Epostix;

enum TimeoutPhase: string
{
    case Connect = 'connect';
    case Read = 'read';
    case OperationDeadline = 'operation_deadline';
}
