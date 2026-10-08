<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Testing;

use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use SpitsOnline\Openprovider\Data\Page;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\Zone;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\ZoneType;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Resources\Zones;

/**
 * The zones of `OpenproviderFake`. A zone that wasn't seeded or created answers
 * like a missing one: with a `RequestFailed` exception with status 404.
 */
class FakeZones extends Zones
{
    public function __construct(
        protected OpenproviderFake $fake,
    ) {
        parent::__construct($fake);
    }

    public function list(int $limit = 100, int $offset = 0, ?string $namePattern = null, bool $withRecords = false): Page
    {
        $names = array_values(array_filter(
            array_keys($this->fake->zoneStore()),
            fn (string $name) => $namePattern === null || Str::is($namePattern, $name),
        ));

        return new Page(
            items: array_map(fn (string $name) => $this->zone($name, $withRecords), array_slice($names, $offset, $limit)),
            total: count($names),
            limit: $limit,
            offset: $offset,
        );
    }

    public function find(string $name, bool $withRecords = true, ?string $provider = null): Zone
    {
        return $this->zone($name, $withRecords);
    }

    public function records(string $zone, ?RecordType $type = null, ?string $provider = null): LazyCollection
    {
        return LazyCollection::make($this->recordsOf($zone))
            ->filter(fn (Record $record) => $type === null || $record->type === $type)
            ->values();
    }

    public function create(
        string $name,
        array $records = [],
        ?string $masterIp = null,
        bool $isDnssecEnabled = false,
        ?string $template = null,
        ?string $provider = null,
    ): void {
        $this->fake->putZone($name, $records);
        $this->fake->recordChange('zone.created', $name);
    }

    public function delete(string $name, ?string $provider = null): void
    {
        $this->recordsOf($name);

        $this->fake->forgetZone($name);
        $this->fake->recordChange('zone.deleted', $name);
    }

    public function addRecords(string $zone, array $records, ?string $provider = null): void
    {
        // Openprovider saves TXT values quoted; so does the fake.
        $this->fake->putZone($zone, [...$this->recordsOf($zone), ...array_map(fn (Record $record) => $record->stored(), $records)]);

        foreach ($records as $record) {
            $this->fake->recordChange('record.added', $zone, $record);
        }
    }

    public function updateRecord(string $zone, Record $original, Record $record, ?string $provider = null): void
    {
        $this->fake->putZone($zone, array_map(
            fn (Record $existing) => $existing->is($original) ? $record->stored() : $existing,
            $this->recordsOf($zone),
        ));

        $this->fake->recordChange('record.updated', $zone, $original, $record);
    }

    public function removeRecords(string $zone, array $records, ?string $provider = null): void
    {
        $this->fake->putZone($zone, array_values(array_filter(
            $this->recordsOf($zone),
            fn (Record $existing) => ! Collection::make($records)->contains(fn (Record $record) => $existing->is($record)),
        )));

        foreach ($records as $record) {
            $this->fake->recordChange('record.removed', $zone, $record);
        }
    }

    protected function zone(string $name, bool $withRecords): Zone
    {
        $records = $this->recordsOf($name);

        return new Zone(
            id: array_search($name, array_keys($this->fake->zoneStore()), true) + 1,
            name: $name,
            type: ZoneType::MASTER,
            isActive: true,
            provider: null,
            records: $withRecords ? $records : [],
            createdAt: null,
            modifiedAt: null,
            raw: [],
        );
    }

    /**
     * @return list<Record>
     */
    protected function recordsOf(string $zone): array
    {
        return $this->fake->zoneStore()[$zone] ?? throw new RequestFailed("Zone `{$zone}` doesn't exist in the Openprovider fake.", 404);
    }
}
