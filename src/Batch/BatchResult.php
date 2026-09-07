<?php

declare(strict_types=1);

namespace Epostix\Batch;

use Epostix\Wire;

final readonly class BatchResult
{
    public function __construct(
        public string $batchId,
        public array $outcomes,
        public array $accepted,
        public array $rejected,
        public array $unresolved,
        public array $acceptedEmailIds,
        public bool $allAccepted,
        public bool $hasUnresolved,
    ) {}

    public static function normalize(array $raw, int $inputCount): self
    {
        $byIndex = [];

        foreach (Wire::asList($raw['results'] ?? []) as $item) {
            $item = Wire::asArray($item);
            $index = Wire::asInt($item['index'] ?? -1);

            if (!array_key_exists('index', $item) || $index < 0) {
                continue;
            }

            $id = Wire::asString($item['id'] ?? '');
            $status = Wire::asString($item['status'] ?? '');

            if ($id !== '' && $status !== 'failed') {
                $byIndex[$index] = new BatchOutcome(
                    $index,
                    BatchOutcomeStatus::Accepted,
                    emailId: $id,
                    sendState: $status === '' ? 'pending' : $status,
                );

                continue;
            }

            $error = $item['error'] ?? null;

            if (is_array($error)) {
                $byIndex[$index] = new BatchOutcome(
                    $index,
                    BatchOutcomeStatus::Rejected,
                    errorType: Wire::asString($error['type'] ?? ''),
                    message: Wire::asString($error['message'] ?? ''),
                );

                continue;
            }

            $byIndex[$index] = new BatchOutcome(
                $index,
                BatchOutcomeStatus::Unresolved,
                reason: UnresolvedReason::StatusFailedWithoutError,
            );
        }

        foreach (Wire::asList($raw['errors'] ?? []) as $failure) {
            $failure = Wire::asArray($failure);
            $index = Wire::asInt($failure['index'] ?? -1);

            if (!array_key_exists('index', $failure) || $index < 0 || array_key_exists($index, $byIndex)) {
                continue;
            }

            $byIndex[$index] = new BatchOutcome(
                $index,
                BatchOutcomeStatus::Rejected,
                errorType: Wire::asString($failure['code'] ?? ''),
                message: Wire::asString($failure['message'] ?? ''),
            );
        }

        $outcomes = [];
        $accepted = [];
        $rejected = [];
        $unresolved = [];
        $acceptedEmailIds = [];

        for ($index = 0; $index < $inputCount; $index += 1) {
            $outcome = $byIndex[$index]
                ?? new BatchOutcome($index, BatchOutcomeStatus::Unresolved, reason: UnresolvedReason::NotReported);

            $outcomes[] = $outcome;

            match ($outcome->status) {
                BatchOutcomeStatus::Accepted => [$accepted[] = $outcome, $acceptedEmailIds[] = $outcome->emailId],
                BatchOutcomeStatus::Rejected => $rejected[] = $outcome,
                BatchOutcomeStatus::Unresolved => $unresolved[] = $outcome,
            };
        }

        return new self(
            Wire::asString($raw['batch_id'] ?? ''),
            $outcomes,
            $accepted,
            $rejected,
            $unresolved,
            $acceptedEmailIds,
            $rejected === [] && $unresolved === [],
            $unresolved !== [],
        );
    }
}
