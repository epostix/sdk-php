<?php

declare(strict_types=1);

namespace Epostix;

final class RetriesExhaustedReason
{
    public const ATTEMPT_BUDGET_EXHAUSTED = 'AttemptBudgetExhausted';
    public const DEADLINE_EXHAUSTED = 'DeadlineExhausted';
    public const RETRY_AFTER_EXCEEDS_DEADLINE = 'RetryAfterExceedsDeadline';
    public const NOT_RETRYABLE = 'NotRetryable';
    public const OPERATION_EXCLUDED = 'OperationExcluded';
    public const BODY_NOT_REPLAYABLE = 'BodyNotReplayable';
    public const CANCELLED = 'Cancelled';
}
