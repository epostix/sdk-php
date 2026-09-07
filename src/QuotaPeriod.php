<?php

declare(strict_types=1);

namespace Epostix;

enum QuotaPeriod: string
{
    case Daily = 'daily';
    case Monthly = 'monthly';
}
