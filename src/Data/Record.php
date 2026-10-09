<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use SpitsOnline\Openprovider\Concerns\ListsFromArray;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Exceptions\InvalidRecord;

/**
 * A DNS record to add to a zone. Build one with `Record::create()`. A record read
 * from a zone is a `ZoneRecord`, which can also update and delete itself.
 */
final readonly class Record
{
    use ListsFromArray;

    /**
     * The priority an MX record gets when you don't pass one. Openprovider requires one.
     */
    public const int DEFAULT_MX_PRIORITY = 10;

    /**
     * @param  string  $name  the host before the zone name, e.g. `www`; empty for the zone itself
     * @param  array<array-key, mixed>  $raw
     *
     * @throws InvalidRecord for an SOA record, which Openprovider generates
     */
    public function __construct(
        public RecordType $type,
        public string $value,
        public string $name = '',
        public int $ttl = Ttl::FIFTEEN_MINUTES->value,
        public ?int $priority = null,
        public array $raw = [],
    ) {
        if (! $type->isEditable()) {
            throw InvalidRecord::readOnly($type);
        }
    }

    /**
     * @param  string  $name  the host before the zone name, e.g. `www`; leave it out for the zone itself
     * @param  ?int  $priority  for MX and SRV records; an MX record gets 10 when you leave it out
     *
     * @throws InvalidRecord for an SOA record, which Openprovider generates
     */
    public static function create(
        RecordType $type,
        string $value,
        string $name = '',
        Ttl $ttl = Ttl::FIFTEEN_MINUTES,
        ?int $priority = null,
    ): self {
        return new self($type, $value, $name, $ttl->value, $priority ?? ($type === RecordType::MX ? self::DEFAULT_MX_PRIORITY : null));
    }

    /**
     * A record from Openprovider's payload, as a route receives it or Openprovider returns it.
     *
     * @param  array<array-key, mixed>  $payload
     *
     * @throws InvalidRecord for an SOA record, which Openprovider generates
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self(
            type: RecordType::from($data->string('type')->value()),
            value: $data->string('value')->value(),
            name: $data->string('name')->value(),
            ttl: $data->integer('ttl'),
            priority: $data->filled('prio') ? $data->integer('prio') : null,
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
            ...($this->priority === null ? [] : ['prio' => $this->priority]),
        ];
    }

    /**
     * The record as Openprovider stores it. Openprovider saves a TXT value wrapped in
     * quotes, and only deletes or updates a TXT record when its value is quoted the
     * same way, so a TXT record you built is quoted to match.
     */
    public function stored(): self
    {
        if ($this->type !== RecordType::TXT || (Str::length($this->value) > 1 && Str::startsWith($this->value, '"') && Str::endsWith($this->value, '"'))) {
            return $this;
        }

        return new self($this->type, Str::wrap($this->value, '"'), $this->name, $this->ttl, $this->priority, $this->raw);
    }

    /**
     * Whether both records describe the same DNS entry, ignoring `$raw` and TXT quotes.
     */
    public function is(self|ZoneRecord $record): bool
    {
        return $this->stored()->toArray() === ($record instanceof ZoneRecord ? $record->toRecord() : $record)->stored()->toArray();
    }
}
