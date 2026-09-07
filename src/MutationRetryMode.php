<?php

declare(strict_types=1);

namespace Epostix;

enum MutationRetryMode: string
{
    case Never = 'Never';
    case OnlyWhenDeduplicated = 'OnlyWhenDeduplicated';
    case Always = 'Always';
}
