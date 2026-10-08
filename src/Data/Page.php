<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

/**
 * One page of a list. `$total` counts every item across all pages.
 *
 * @template TItem
 */
final readonly class Page
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $limit,
        public int $offset,
    ) {}

    public function hasMore(): bool
    {
        return $this->offset + count($this->items) < $this->total;
    }
}
