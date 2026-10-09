<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Concerns\ConfirmsChanges;
use SpitsOnline\Openprovider\Concerns\Paginates;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\ZoneRecord;
use SpitsOnline\Openprovider\Enums\Provider;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Openprovider;

/**
 * The records of one zone: `Openprovider::zone('example.com')->records()`. The SOA
 * record isn't one of them: Openprovider generates it, so it can't be changed. Read
 * it from `Openprovider::zone('example.com')->get()->soa`.
 */
class ZoneRecords
{
    use ConfirmsChanges;
    use Paginates;

    /**
     * The largest page Openprovider returns for records.
     */
    protected const int PAGE_SIZE = 500;

    public function __construct(
        protected Openprovider $openprovider,
        protected string $zone,
        protected ?Provider $provider = null,
    ) {}

    /**
     * Every record, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, ZoneRecord>
     */
    public function get(): LazyCollection
    {
        return $this->fetch(null);
    }

    /**
     * The records of one type, filtered by Openprovider.
     *
     * @return LazyCollection<int, ZoneRecord>
     */
    public function ofType(RecordType $type): LazyCollection
    {
        return $this->fetch($type);
    }

    /**
     * Add one or more records. One request.
     */
    public function add(Record $record, Record ...$records): void
    {
        // array_values: spread records can arrive with string keys, which JSON would turn into an object.
        $this->changeRecords('add', array_values(array_map(fn (Record $record) => $this->relative($record)->toArray(), [$record, ...$records])), "add records to `{$this->zone}`");
    }

    /**
     * Replace a record with another one. One request. `$original` is a record read
     * from the zone, or one you built that matches it.
     */
    public function update(Record|ZoneRecord $original, Record $record): void
    {
        $this->changeRecords('update', [[
            'original_record' => $this->matching($original),
            'record' => $this->relative($record)->toArray(),
        ]], "update a record of `{$this->zone}`");
    }

    /**
     * Delete one or more records. One request, however many records you pass.
     *
     * Unlike Eloquent's `$user->posts()->delete()`, this never deletes every record:
     * it needs at least one, and deletes only the ones you pass. Pass records read
     * from the zone, or ones you built that match them.
     */
    public function delete(Record|ZoneRecord $record, Record|ZoneRecord ...$records): void
    {
        $this->changeRecords('remove', array_values(array_map($this->matching(...), [$record, ...$records])), "delete records from `{$this->zone}`");
    }

    /**
     * @return LazyCollection<int, ZoneRecord>
     */
    protected function fetch(?RecordType $type): LazyCollection
    {
        // Filter after paging, so the offsets count every record Openprovider returned.
        return $this->paginate(function (int $offset) use ($type): array {
            $data = $this->openprovider->request('get', $this->path().'/records', "list the records of `{$this->zone}`", Arr::whereNotNull([
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'type' => $type?->value,
                'zone_provider' => $this->provider?->value,
            ]));

            return [$data->array('results'), $data->integer('total')];
        })
            ->filter(fn (mixed $record) => is_array($record) && Arr::get($record, 'type') !== RecordType::SOA->value)
            ->map(fn (array $record) => ZoneRecord::fromArray($record, $this->zone, $this->provider, $this->openprovider))
            ->values();
    }

    /**
     * A record the way Openprovider matches an existing one: by its name relative to
     * the zone, and with a TXT value quoted the way Openprovider stores it. Openprovider
     * returns full names (`www.example.com`), but a full name in an update or delete
     * matches nothing: an update then adds the new record and keeps the old one, and a
     * delete silently does nothing, both while reporting success. Seen on production.
     *
     * @return array<string, mixed>
     */
    protected function matching(Record|ZoneRecord $record): array
    {
        return $this->relative($record instanceof ZoneRecord ? $record->toRecord() : $record)->stored()->toArray();
    }

    /**
     * The record with its name relative to the zone: `www.example.com` becomes `www`,
     * and the zone name itself an empty name.
     */
    protected function relative(Record $record): Record
    {
        $name = ZoneRecord::relativeName($record->name, $this->zone);

        return $name === $record->name ? $record : new Record($record->type, $record->value, $name, $record->ttl, $record->priority, $record->raw);
    }

    /**
     * @param  'add'|'update'|'remove'  $operation
     * @param  list<array<string, mixed>>  $records
     */
    protected function changeRecords(string $operation, array $records, string $action): void
    {
        $this->change('put', $this->path(), $action, body: [
            'records' => [$operation => $records],
            'provider' => $this->provider?->value,
        ]);
    }

    protected function path(): string
    {
        return 'dns/zones/'.rawurlencode($this->zone);
    }
}
