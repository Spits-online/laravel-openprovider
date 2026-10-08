<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Concerns\ListsFromArray;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;

/**
 * A DNS record. Build one with `Record::create()`; records read from Openprovider keep
 * the full payload in `$raw`.
 */
final readonly class Record
{
    use ListsFromArray;

    /**
     * @param  string  $name  the host before the zone name, e.g. `www`; empty for the zone itself
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public RecordType $type,
        public string $value,
        public string $name = '',
        public int $ttl = Ttl::FifteenMinutes->value,
        public ?int $prio = null,
        public array $raw = [],
    ) {}

    /**
     * @param  ?int  $prio  the priority, which Openprovider requires for MX records
     */
    public static function create(
        RecordType $type,
        string $value,
        string $name = '',
        Ttl $ttl = Ttl::FifteenMinutes,
        ?int $prio = null,
    ): self {
        return new self($type, $value, $name, $ttl->value, $prio);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self(
            type: RecordType::from($data->string('type')->value()),
            value: $data->string('value')->value(),
            name: $data->string('name')->value(),
            ttl: $data->integer('ttl'),
            prio: $data->filled('prio') ? $data->integer('prio') : null,
            raw: $payload,
        );
    }

    /**
     * The record as Openprovider expects it. An empty name and a missing priority are left out.
     *
     * @return array{name?: string, type: string, value: string, ttl: int, prio?: int}
     */
    public function toArray(): array
    {
        return [
            ...($this->name === '' ? [] : ['name' => $this->name]),
            'type' => $this->type->value,
            'value' => $this->value,
            'ttl' => $this->ttl,
            ...($this->prio === null ? [] : ['prio' => $this->prio]),
        ];
    }

    /**
     * Whether both records describe the same DNS entry, ignoring `$raw`.
     */
    public function is(self $record): bool
    {
        return $this->toArray() === $record->toArray();
    }
}
