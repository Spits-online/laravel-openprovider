<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
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
        public int $ttl = Ttl::FIFTEEN_MINUTES->value,
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
        Ttl $ttl = Ttl::FIFTEEN_MINUTES,
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
     * The record as Openprovider stores it. Openprovider saves a TXT value wrapped in
     * quotes, and only removes or updates a TXT record when its value is quoted the
     * same way, so a record built with `Record::create()` is quoted to match.
     */
    public function stored(): self
    {
        if ($this->type !== RecordType::TXT || (Str::length($this->value) > 1 && Str::startsWith($this->value, '"') && Str::endsWith($this->value, '"'))) {
            return $this;
        }

        return new self($this->type, Str::wrap($this->value, '"'), $this->name, $this->ttl, $this->prio, $this->raw);
    }

    /**
     * Whether both records describe the same DNS entry, ignoring `$raw` and TXT quotes.
     */
    public function is(self $record): bool
    {
        return $this->stored()->toArray() === $record->stored()->toArray();
    }
}
