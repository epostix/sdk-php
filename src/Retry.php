<?php

declare(strict_types=1);

namespace Epostix;

final class Retry
{
    public static function classify(ClassifyContext $context): RetryDecision
    {
        if ($context->attemptNumber > $context->policy->maximumRetries) {
            return RetryDecision::stop(RetriesExhaustedReason::ATTEMPT_BUDGET_EXHAUSTED);
        }

        if ($context->remainingMilliseconds <= $context->policy->connectTimeoutMilliseconds) {
            return RetryDecision::stop(RetriesExhaustedReason::DEADLINE_EXHAUSTED);
        }

        $error = $context->error;

        if (!$error instanceof EpostixApiError) {
            return self::guardDeadline(self::classForTransient($context), $context);
        }

        if (in_array($error->type, EpostixErrorType::NEVER_RETRY, true)) {
            return RetryDecision::stop(RetriesExhaustedReason::NOT_RETRYABLE);
        }

        if ($error instanceof EpostixRateLimitError || $error instanceof EpostixIdempotencyInProgressError) {
            $retryAfter = $error->responseMetadata->retryAfterSeconds;

            if (!$context->policy->honorRetryAfter || $retryAfter === null) {
                return self::guardDeadline(self::classForTransient($context), $context);
            }

            $delay = $retryAfter * 1000.0;

            return self::fitsDeadline($delay, $context)
                ? RetryDecision::again($delay)
                : RetryDecision::stop(RetriesExhaustedReason::RETRY_AFTER_EXCEEDS_DEADLINE);
        }

        $retryableStatus = $error->status === 408 || $error->status >= 500;
        $retryableType = $error->type === EpostixErrorType::ATTACHMENT_FETCH_FAILED;

        if (!$retryableStatus && !$retryableType) {
            return RetryDecision::stop(RetriesExhaustedReason::NOT_RETRYABLE);
        }

        return self::guardDeadline(self::classForTransient($context), $context);
    }

    private static function classForTransient(ClassifyContext $context): RetryDecision
    {
        $delay = self::fullJitter($context->policy, $context->attemptNumber, $context->random);

        return match ($context->retryClass) {
            RetryClass::SafeRead, RetryClass::NaturallyIdempotent => RetryDecision::again($delay),
            RetryClass::Streaming => $context->responseStarted
                ? RetryDecision::stop(RetriesExhaustedReason::NOT_RETRYABLE)
                : RetryDecision::again($delay),
            RetryClass::ExcludedMutation => $context->bytesWereWritten
                ? RetryDecision::stop(RetriesExhaustedReason::OPERATION_EXCLUDED)
                : RetryDecision::again($delay),
            RetryClass::DeduplicatedAdmission => self::classForAdmission($context, $delay),
        };
    }

    private static function classForAdmission(ClassifyContext $context, float $delay): RetryDecision
    {
        if ($context->policy->mutationRetry === MutationRetryMode::Never) {
            return RetryDecision::stop(RetriesExhaustedReason::OPERATION_EXCLUDED);
        }

        if ($context->policy->mutationRetry === MutationRetryMode::Always) {
            return RetryDecision::again($delay);
        }

        if (!$context->hasIdempotencyKey) {
            return RetryDecision::stop(RetriesExhaustedReason::OPERATION_EXCLUDED);
        }

        if (!$context->bodyIsReplayable) {
            return RetryDecision::stop(RetriesExhaustedReason::BODY_NOT_REPLAYABLE);
        }

        return RetryDecision::again($delay);
    }

    private static function fullJitter(RetryPolicy $policy, int $attemptNumber, RandomSource $random): float
    {
        $ceiling = min(
            $policy->backoffMaximumMilliseconds,
            $policy->backoffBaseMilliseconds * ($policy->backoffMultiplier ** ($attemptNumber - 1)),
        );

        return $random->fraction() * $ceiling;
    }

    private static function fitsDeadline(float $delay, ClassifyContext $context): bool
    {
        return $delay + $context->policy->connectTimeoutMilliseconds <= $context->remainingMilliseconds;
    }

    private static function guardDeadline(RetryDecision $decision, ClassifyContext $context): RetryDecision
    {
        if ($decision->shouldRetry && !self::fitsDeadline($decision->delayMilliseconds, $context)) {
            return RetryDecision::stop(RetriesExhaustedReason::DEADLINE_EXHAUSTED);
        }

        return $decision;
    }
}
