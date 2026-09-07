<?php

declare(strict_types=1);

namespace Epostix;

final class EpostixValidationError extends EpostixApiError
{
    public function fieldErrors(): array
    {
        return $this->details;
    }
}
