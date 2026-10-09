<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Enums\RecordType;

/**
 * A zone's SOA record. Openprovider generates it, so it can be read but not added,
 * changed or deleted, and it isn't one of the zone's `records()`.
 */
final readonly class SoaRecord
{
    public RecordType $type;

    /**
     * Always null: SOA records have no priority. Here so an SOA record reads like any other record.
     */
    public ?int $priority;

    /**
     * @param  string  $value  e.g. `ns1.example.com dns.openprovider.eu 2026100803 10800 3600 604800 3600`
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $name,
        public string $value,
        public int $ttl,
        public array $raw = [],
    ) {
        $this->type = RecordType::SOA;
        $this->priority = null;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self(
            name: $data->string('name')->value(),
            value: $data->string('value')->value(),
            ttl: $data->integer('ttl'),
            raw: $payload,
        );
    }
}
