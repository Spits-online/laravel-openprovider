<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Concerns;

use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Data\Page;

trait Paginates
{
    /**
     * Walk the pages lazily: the next page is only fetched once the items of the
     * previous one have been used.
     *
     * @template TItem
     *
     * @param  callable(int $offset): Page<TItem>  $page
     * @return LazyCollection<int, TItem>
     */
    protected function paginate(callable $page): LazyCollection
    {
        return LazyCollection::make(function () use ($page) {
            $offset = 0;

            do {
                $current = $page($offset);

                // Not `yield from`: that would restart the keys at 0 on every page.
                foreach ($current->items as $item) {
                    yield $item;
                }

                $offset += count($current->items);
            } while ($current->items !== [] && $current->hasMore());
        });
    }
}
