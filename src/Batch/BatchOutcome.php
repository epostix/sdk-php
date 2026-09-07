<?php

declare(strict_types=1);

namespace Epostix\Batch;

final readonly class BatchOutcome
{
    public function __construct(
        public int $index,
        public BatchOutcomeStatus $status,
        public ?string $emailId = null,
        public ?string $sendState = null,
        public ?string $errorType = null,
        public ?string $message = null,
        public ?UnresolvedReason $reason = null,
    ) {}
}
