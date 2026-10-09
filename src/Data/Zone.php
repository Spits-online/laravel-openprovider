<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Concerns\ReadsDates;
use SpitsOnline\Openprovider\Enums\Provider;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\ZoneType;
use SpitsOnline\Openprovider\Openprovider;

/**
 * A DNS zone as Openprovider returns it. `$records` holds the records you can change,
 * and `$soa` the read-only SOA record; both are empty when the zone was fetched
 * without its records. Dates are read in `openprovider.timezone` (see `ReadsDates`);
 * `$raw` holds the full payload.
 */
final readonly class Zone
{
    use ReadsDates;

    /**
     * @param  list<ZoneRecord>  $records
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?ZoneType $type,
        public bool $isActive,
        public ?Provider $provider,
        public array $records,
        public ?SoaRecord $soa,
        public ?CarbonInterface $createdAt,
        public ?CarbonInterface $modifiedAt,
        public array $raw,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @internal
     */
    public static function fromArray(array $payload, Openprovider $openprovider): self
    {
        $data = new Fluent($payload);
        $name = $data->string('name')->value();
        $provider = $data->enum('provider', Provider::class);

        $payloads = $data->collect('records')->filter(fn (mixed $record) => is_array($record));
        $isSoa = fn (array $record) => Arr::get($record, 'type') === RecordType::SOA->value;
        $soa = $payloads->first($isSoa);

        return new self(
            id: $data->integer('id'),
            name: $name,
            type: $data->enum('type', ZoneType::class),
            isActive: $data->boolean('active'),
            provider: $provider,
            records: array_values($payloads->reject($isSoa)->map(fn (array $record) => ZoneRecord::fromArray($record, $name, $provider, $openprovider))->all()),
            soa: $soa === null ? null : SoaRecord::fromArray($soa),
            createdAt: self::date($data, 'creation_date'),
            modifiedAt: self::date($data, 'modification_date'),
            raw: $payload,
        );
    }

    /**
     * The zone in Openprovider's own keys. `records` lists every record in the order
     * Openprovider returned them, the SOA record included.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $records = (new Fluent($this->raw))->collect('records')
            ->filter(fn (mixed $record) => is_array($record))
            ->map(fn (array $record) => Arr::whereNotNull([
                'name' => Arr::get($record, 'name'),
                'type' => Arr::get($record, 'type'),
                'value' => Arr::get($record, 'value'),
                'ttl' => Arr::get($record, 'ttl'),
                'prio' => Arr::get($record, 'prio'),
            ]))
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type?->value,
            'active' => $this->isActive,
            'provider' => $this->provider?->value,
            'records' => $records,
            'creation_date' => $this->createdAt?->format(self::DATE_FORMAT),
            'modification_date' => $this->modifiedAt?->format(self::DATE_FORMAT),
        ];
    }
}
