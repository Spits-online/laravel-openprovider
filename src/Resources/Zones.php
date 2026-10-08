<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Concerns\Paginates;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Page;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\Zone;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\ZoneType;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Openprovider;

/**
 * DNS zones and their records. Pass `provider: 'sectigo'` to work with a premium DNS zone.
 */
class Zones
{
    use Paginates;

    /**
     * The largest page Openprovider returns for zones and records.
     */
    protected const int PAGE_SIZE = 500;

    public function __construct(
        protected Openprovider $openprovider,
    ) {}

    /**
     * One page of zones. `$namePattern` accepts `*` as a wildcard.
     *
     * @return Page<Zone>
     */
    public function list(int $limit = 100, int $offset = 0, ?string $namePattern = null, bool $withRecords = false): Page
    {
        $data = $this->openprovider->request('get', 'dns/zones', 'list the zones', Arr::whereNotNull([
            'limit' => $limit,
            'offset' => $offset,
            'name_pattern' => $namePattern,
            'with_records' => $withRecords,
        ]));

        return new Page(
            items: Zone::listFrom($data->array('results')),
            total: $data->integer('total'),
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * Every zone, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, Zone>
     */
    public function all(?string $namePattern = null): LazyCollection
    {
        return $this->paginate(fn (int $offset) => $this->list(self::PAGE_SIZE, $offset, $namePattern));
    }

    public function find(string $name, bool $withRecords = true, ?string $provider = null): Zone
    {
        return Zone::fromArray($this->openprovider->request('get', $this->path($name), "find zone `{$name}`", Arr::whereNotNull([
            'with_records' => $withRecords,
            'provider' => $provider,
        ]))->toArray());
    }

    /**
     * Every record of a zone, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, Record>
     */
    public function records(string $zone, ?RecordType $type = null, ?string $provider = null): LazyCollection
    {
        return $this->paginate(function (int $offset) use ($zone, $type, $provider): Page {
            $data = $this->openprovider->request('get', $this->path($zone).'/records', "list the records of `{$zone}`", Arr::whereNotNull([
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'type' => $type?->value,
                'zone_provider' => $provider,
            ]));

            return new Page(
                items: Record::listFrom($data->array('results')),
                total: $data->integer('total'),
                limit: self::PAGE_SIZE,
                offset: $offset,
            );
        });
    }

    /**
     * Create a zone. Pass `$masterIp` to create a slave zone that copies its records
     * from that server.
     *
     * @param  list<Record>  $records
     */
    public function create(
        string $name,
        array $records = [],
        ?string $masterIp = null,
        bool $isDnssecEnabled = false,
        ?string $template = null,
        ?string $provider = null,
    ): void {
        $this->write('post', 'dns/zones', "create zone `{$name}`", [
            'domain' => DomainName::parse($name)->toArray(),
            'type' => ($masterIp === null ? ZoneType::Master : ZoneType::Slave)->value,
            'master_ip' => $masterIp,
            'records' => $this->serialize($records) ?: null,
            'secured' => $isDnssecEnabled ?: null,
            'template_name' => $template,
            'provider' => $provider,
        ]);
    }

    /**
     * Delete a zone. Openprovider can't restore a deleted zone.
     */
    public function delete(string $name, ?string $provider = null): void
    {
        $data = $this->openprovider->request('delete', $this->path($name), "delete zone `{$name}`", Arr::whereNotNull([
            'provider' => $provider,
        ]));

        $this->ensureSuccess($data, "delete zone `{$name}`");
    }

    /**
     * @param  list<Record>  $records
     */
    public function addRecords(string $zone, array $records, ?string $provider = null): void
    {
        $this->updateZone($zone, ['add' => $this->serialize($records)], $provider, "add records to `{$zone}`");
    }

    public function updateRecord(string $zone, Record $original, Record $record, ?string $provider = null): void
    {
        $this->updateZone($zone, ['update' => [[
            'original_record' => $original->toArray(),
            'record' => $record->toArray(),
        ]]], $provider, "update a record of `{$zone}`");
    }

    /**
     * @param  list<Record>  $records
     */
    public function removeRecords(string $zone, array $records, ?string $provider = null): void
    {
        $this->updateZone($zone, ['remove' => $this->serialize($records)], $provider, "remove records from `{$zone}`");
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $changes
     */
    protected function updateZone(string $zone, array $changes, ?string $provider, string $action): void
    {
        $this->write('put', $this->path($zone), $action, ['records' => $changes, 'provider' => $provider]);
    }

    /**
     * Send a change and make sure Openprovider reports it as done. Null fields are left out.
     *
     * @param  'post'|'put'  $method
     * @param  array<string, mixed>  $body
     */
    protected function write(string $method, string $path, string $action, array $body): void
    {
        $this->ensureSuccess($this->openprovider->request($method, $path, $action, body: Arr::whereNotNull($body)), $action);
    }

    /**
     * @param  Fluent<array-key, mixed>  $data
     */
    protected function ensureSuccess(Fluent $data, string $action): void
    {
        if ($data->get('success') !== true) {
            throw RequestFailed::unexpected($action, '`success: true`', $data->toArray());
        }
    }

    /**
     * @param  list<Record>  $records
     * @return list<array<string, mixed>>
     */
    protected function serialize(array $records): array
    {
        return array_map(fn (Record $record) => $record->toArray(), $records);
    }

    protected function path(string $zone): string
    {
        return 'dns/zones/'.rawurlencode($zone);
    }
}
