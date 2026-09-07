<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\Batch\BatchOutcomeStatus;
use Epostix\Batch\BatchResult;
use Epostix\Batch\UnresolvedReason;
use PHPUnit\Framework\TestCase;

final class BatchTest extends TestCase
{
    public function testThereIsExactlyOneOutcomePerInputIndex(): void
    {
        $result = BatchResult::normalize([
            'batch_id' => 'bat_1',
            'results' => [
                ['index' => 0, 'id' => 'eml_1', 'status' => 'queued'],
            ],
        ], 3);

        self::assertCount(3, $result->outcomes);
        self::assertSame([0, 1, 2], array_map(static fn ($o): int => $o->index, $result->outcomes));
    }

    public function testAMessageTheServerNeverReportedIsUnresolvedNotAccepted(): void
    {
        $result = BatchResult::normalize(['batch_id' => 'bat_1', 'results' => []], 2);

        self::assertCount(2, $result->unresolved);
        self::assertSame(UnresolvedReason::NotReported, $result->unresolved[0]->reason);
        self::assertFalse($result->allAccepted);
        self::assertTrue($result->hasUnresolved);
    }

    public function testAFailedStatusWithoutAnErrorIsUnresolved(): void
    {
        $result = BatchResult::normalize([
            'results' => [['index' => 0, 'id' => 'eml_1', 'status' => 'failed']],
        ], 1);

        self::assertSame(BatchOutcomeStatus::Unresolved, $result->outcomes[0]->status);
        self::assertSame(UnresolvedReason::StatusFailedWithoutError, $result->outcomes[0]->reason);
    }

    public function testPerIndexErrorsBecomeRejections(): void
    {
        $result = BatchResult::normalize([
            'batch_id' => 'bat_1',
            'results' => [['index' => 0, 'id' => 'eml_1', 'status' => 'queued']],
            'errors' => [['index' => 1, 'code' => 'validation_error', 'message' => 'to is required']],
        ], 2);

        self::assertSame(BatchOutcomeStatus::Accepted, $result->outcomes[0]->status);
        self::assertSame(BatchOutcomeStatus::Rejected, $result->outcomes[1]->status);
        self::assertSame('validation_error', $result->outcomes[1]->errorType);
        self::assertSame(['eml_1'], $result->acceptedEmailIds);
        self::assertFalse($result->allAccepted);
        self::assertFalse($result->hasUnresolved);
    }

    public function testAllAcceptedIsDerivedNotReported(): void
    {
        $result = BatchResult::normalize([
            'batch_id' => 'bat_1',
            'results' => [
                ['index' => 0, 'id' => 'eml_1', 'status' => 'queued'],
                ['index' => 1, 'id' => 'eml_2', 'status' => 'queued'],
            ],
        ], 2);

        self::assertTrue($result->allAccepted);
        self::assertSame(['eml_1', 'eml_2'], $result->acceptedEmailIds);
    }

    public function testAResultWithoutAnIdCarriesTheEmbeddedError(): void
    {
        $result = BatchResult::normalize([
            'results' => [
                ['index' => 0, 'status' => 'failed', 'error' => ['type' => 'invalid_from_address', 'message' => 'bad from']],
            ],
        ], 1);

        self::assertSame(BatchOutcomeStatus::Rejected, $result->outcomes[0]->status);
        self::assertSame('invalid_from_address', $result->outcomes[0]->errorType);
        self::assertSame('bad from', $result->outcomes[0]->message);
    }
}
