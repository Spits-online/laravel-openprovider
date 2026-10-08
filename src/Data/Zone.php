<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Concerns\ListsFromArray;
use SpitsOnline\Openprovider\Concerns\ReadsDates;
use SpitsOnline\Openprovider\Enums\ZoneType;

/**
 * A DNS zone as Openprovider returns it. `$records` is empty unless the zone was
 * fetched with its records. Dates are read in `openprovider.timezone` (see
 * `ReadsDates`); `$raw` holds the full payload.
 */
final readonly class Zone
{
    use ListsFromArray;
    use ReadsDates;

    /**
     * @param  list<Record>  $records
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?ZoneType $type,
        public bool $isActive,
        public ?string $provider,
        public array $records,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $modifiedAt,
        public array $raw,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self(
            id: $data->integer('id'),
            name: $data->string('name')->value(),
            type: $data->enum('type', ZoneType::class),
            isActive: $data->boolean('active'),
            provider: $data->string('provider')->value() ?: null,
            records: Record::listFrom($data->array('records')),
            createdAt: self::date($data, 'creation_date'),
            modifiedAt: self::date($data, 'modification_date'),
            raw: $payload,
        );
    }

    /**
     * The zone in Openprovider's own keys, with the records as `Record::toArray()` writes them.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'active' => $this->isActive,
            'provider' => $this->provider,
            'records' => array_map(fn (Record $record) => $record->toArray(), $this->records),
            'creation_date' => $this->createdAt?->format(self::DATE_FORMAT),
            'modification_date' => $this->modifiedAt?->format(self::DATE_FORMAT),
        ];
    }
}
