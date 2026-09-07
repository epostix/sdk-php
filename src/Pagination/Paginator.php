<?php

declare(strict_types=1);

namespace Epostix\Pagination;

use Epostix\EpostixConfigurationError;
use Epostix\EpostixPaginationError;
use Epostix\ExecuteInput;
use Epostix\OperationSpec;
use Epostix\PaginationFailureReason;
use Epostix\RequestOptions;
use Epostix\Transport;
use Epostix\Wire;

final class Paginator
{
    public const MAXIMUM_PAGES = 10000;

    private readonly array $filters;

    public function __construct(
        private readonly Transport $transport,
        private readonly OperationSpec $spec,
        private readonly array $path,
        array $query,
        private readonly string $cursorParam,
        private readonly \Closure $decodeItem,
        private readonly ?RequestOptions $options = null,
    ) {
        if (array_key_exists('ending_before', $query) && $query['ending_before'] !== null) {
            throw new EpostixConfigurationError(
                $spec->id . ': ending_before cannot be used with an iterator, which walks forward only.'
                    . ' Use the single-page method instead.',
            );
        }

        unset($query['ending_before']);

        $this->filters = $query;
    }

    public function iteratePages(): \Generator
    {
        $cursor = null;
        $pageNumber = 0;

        while (true) {
            $pageNumber += 1;

            if ($pageNumber > self::MAXIMUM_PAGES) {
                throw new EpostixPaginationError(
                    PaginationFailureReason::PageLimitExceeded,
                    $this->spec->id,
                    $this->spec->id . ': stopped after ' . self::MAXIMUM_PAGES . ' pages',
                );
            }

            $query = $this->filters;

            if ($cursor !== null) {
                $query[$this->cursorParam] = $cursor;
            }

            $result = $this->transport->execute(
                $this->spec,
                new ExecuteInput(path: $this->path, query: $query),
                $this->options,
            );

            $raw = Wire::asArray($result->body);
            $items = [];

            foreach (Wire::asList($raw['data'] ?? []) as $item) {
                $items[] = ($this->decodeItem)(Wire::asArray($item));
            }

            $hasMore = ($raw['has_more'] ?? false) === true;
            $nextCursor = array_key_exists('next_cursor', $raw) && is_string($raw['next_cursor'])
                && $raw['next_cursor'] !== ''
                ? $raw['next_cursor']
                : null;

            yield new Page($items, $hasMore, $nextCursor, $hasMore && $nextCursor === null, $result->metadata);

            if (!$hasMore) {
                return;
            }

            if ($nextCursor === null) {
                throw new EpostixPaginationError(
                    PaginationFailureReason::MissingCursor,
                    $this->spec->id,
                    $this->spec->id . ': the server reported more results but returned no cursor',
                );
            }

            if ($cursor !== null && $nextCursor === $cursor) {
                throw new EpostixPaginationError(
                    PaginationFailureReason::RepeatedCursor,
                    $this->spec->id,
                    $this->spec->id . ': the server returned the same cursor it was given',
                );
            }

            $cursor = $nextCursor;
        }
    }

    public function iterate(): \Generator
    {
        foreach ($this->iteratePages() as $page) {
            foreach ($page->data as $item) {
                yield $item;
            }
        }
    }

    public function collect(int $maximumItems): Collection
    {
        if ($maximumItems <= 0) {
            throw new EpostixConfigurationError('collect requires a positive maximumItems');
        }

        $items = [];
        $lastCursor = null;

        foreach ($this->iteratePages() as $page) {
            $lastCursor = $page->nextCursor;

            foreach ($page->data as $item) {
                if (count($items) >= $maximumItems) {
                    return new Collection($items, true, $lastCursor);
                }

                $items[] = $item;
            }

            if (count($items) >= $maximumItems && $page->hasMore) {
                return new Collection($items, true, $lastCursor);
            }
        }

        return new Collection($items, false, $lastCursor);
    }
}
