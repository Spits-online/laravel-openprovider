<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Concerns;

/**
 * Builds a list of data objects from a list of Openprovider payloads, skipping
 * anything that isn't an object.
 */
trait ListsFromArray
{
    /**
     * @param  array<array-key, mixed>  $payloads
     * @return list<static>
     */
    public static function listFrom(array $payloads): array
    {
        $objects = [];

        foreach ($payloads as $payload) {
            if (is_array($payload)) {
                $objects[] = static::fromArray($payload);
            }
        }

        return $objects;
    }
}
