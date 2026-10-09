<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Concerns;

use Illuminate\Support\LazyCollection;

trait Paginates
{
    /**
     * Walk the pages lazily: the next page is only fetched once the items of the
     * previous one have been used.
     *
     * @template TItem
     *
     * @param  callable(int $offset): array{array<array-key, TItem>, int}  $page  the items from `$offset`, and the total across all pages
     * @return LazyCollection<int, TItem>
     */
    protected function paginate(callable $page): LazyCollection
    {
        return LazyCollection::make(function () use ($page) {
            $offset = 0;

            do {
                [$items, $total] = $page($offset);

                // Not `yield from`: that would restart the keys at 0 on every page.
                foreach ($items as $item) {
                    yield $item;
                }

                $offset += count($items);
            } while ($items !== [] && $offset < $total);
        });
    }
}
