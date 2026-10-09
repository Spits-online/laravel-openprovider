<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Testing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use SpitsOnline\Openprovider\Data\Domain;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\ZoneRecord;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\ZoneType;
use SpitsOnline\Openprovider\Exceptions\OpenproviderException;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Openprovider;

/**
 * An in-memory Openprovider for testing apps. Enable it with `Openprovider::fake()`,
 * seed it with `withZone()` and `withDomain()`, and assert on what changed.
 *
 * It answers the same requests the real client sends, so everything works on it the
 * way it works on Openprovider: records are stored under their full name
 * (`www.example.com`) with TXT values quoted, and a zone or domain that doesn't exist
 * answers with a `RequestFailed` exception with status 404. Seeded domains are
 * `in use` for `check()`; every other name is `free`.
 */
class OpenproviderFake extends Openprovider
{
    public const string AUTH_CODE = 'fake-auth-code';

    /** @var array<string, array{type: string, provider: ?string, records: list<array<string, mixed>>}> */
    protected array $zones = [];

    /** @var array<int, array<array-key, mixed>> */
    protected array $domains = [];

    /** @var list<array{action: string, subject: int|string, arguments: list<mixed>}> */
    protected array $changes = [];

    public function __construct()
    {
        parent::__construct('fake', 'fake', null, 'https://openprovider.test');
    }

    public function withZone(string $name, Record ...$records): static
    {
        $this->zones[$name] = [
            'type' => ZoneType::MASTER->value,
            'provider' => null,
            'records' => array_values(array_map(fn (Record $record) => $this->stored($name, ['name' => ZoneRecord::relativeName($record->name, $name)] + $record->toArray()), $records)),
        ];

        return $this;
    }

    public function withDomain(Domain|string $domain): static
    {
        $payload = $domain instanceof Domain ? $domain->raw : [];
        $name = $domain instanceof Domain ? $domain->name : DomainName::parse($domain);
        $id = $domain instanceof Domain ? $domain->id : $this->nextDomainId();

        $this->domains[$id] = ['id' => $id, 'domain' => $name->toArray(), 'status' => 'ACT'] + $payload;

        return $this;
    }

    public function assertZoneCreated(string $name): void
    {
        $this->assertChanged('zone.created', $name, null, "Zone `{$name}` was not created.");
    }

    public function assertZoneDeleted(string $name): void
    {
        $this->assertChanged('zone.deleted', $name, null, "Zone `{$name}` was not deleted.");
    }

    /**
     * @param  (callable(Record): bool)|null  $callback  receives each record as it was sent
     */
    public function assertRecordAdded(string $zone, ?callable $callback = null): void
    {
        $this->assertChanged('record.added', $zone, $callback, "No matching record was added to `{$zone}`.");
    }

    /**
     * @param  (callable(Record $original, Record $record): bool)|null  $callback
     */
    public function assertRecordUpdated(string $zone, ?callable $callback = null): void
    {
        $this->assertChanged('record.updated', $zone, $callback, "No matching record of `{$zone}` was updated.");
    }

    /**
     * @param  (callable(Record): bool)|null  $callback  receives each record as it was sent
     */
    public function assertRecordDeleted(string $zone, ?callable $callback = null): void
    {
        $this->assertChanged('record.deleted', $zone, $callback, "No matching record was deleted from `{$zone}`.");
    }

    public function assertDomainRegistered(string $name): void
    {
        $this->assertChanged('domain.registered', $name, null, "Domain `{$name}` was not registered.");
    }

    public function assertDomainTransferred(string $name): void
    {
        $this->assertChanged('domain.transferred', $name, null, "Domain `{$name}` was not transferred.");
    }

    /**
     * @param  (callable(array<string, mixed> $changes): bool)|null  $callback  receives the changed fields in Openprovider's keys, e.g. `['is_locked' => true]`
     */
    public function assertDomainUpdated(int $id, ?callable $callback = null): void
    {
        $this->assertChanged('domain.updated', $id, $callback, "Domain {$id} was not updated.");
    }

    public function assertDomainRenewed(int $id): void
    {
        $this->assertChanged('domain.renewed', $id, null, "Domain {$id} was not renewed.");
    }

    public function assertDomainRestored(int $id): void
    {
        $this->assertChanged('domain.restored', $id, null, "Domain {$id} was not restored.");
    }

    public function assertDomainDeleted(int $id): void
    {
        $this->assertChanged('domain.deleted', $id, null, "Domain {$id} was not deleted.");
    }

    public function assertNothingChanged(): void
    {
        PHPUnit::assertSame([], $this->changes, 'Openprovider was changed unexpectedly.');
    }

    /**
     * Answer a request the way Openprovider would, from the seeded data.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     * @return Fluent<array-key, mixed>
     *
     * @internal
     */
    public function request(string $method, string $path, string $action, array $query = [], array $body = []): Fluent
    {
        $query = new Fluent($query);
        $body = new Fluent($body);
        $segments = explode('/', $path);
        $route = Str::upper($method).' '.preg_replace(['#^dns/zones/[^/]+#', '#^domains/\d+#'], ['dns/zones/{name}', 'domains/{id}'], $path);
        $zone = isset($segments[2]) && $segments[0] === 'dns' ? rawurldecode($segments[2]) : '';
        $id = $segments[0] === 'domains' && isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : 0;

        return new Fluent(match ($route) {
            'GET dns/zones' => $this->listZones($query),
            'POST dns/zones' => $this->createZone($body),
            'GET dns/zones/{name}' => $this->zonePayload($zone, $query->boolean('with_records')),
            'DELETE dns/zones/{name}' => $this->deleteZone($zone),
            'PUT dns/zones/{name}' => $this->changeRecords($zone, $body),
            'GET dns/zones/{name}/records' => $this->listRecords($zone, $query),
            'GET domains' => $this->listDomains($query),
            'POST domains' => $this->addDomain('domain.registered', $body),
            'POST domains/transfer' => $this->addDomain('domain.transferred', $body),
            'POST domains/check' => $this->checkDomains($body),
            'GET domains/{id}' => $this->domainPayload($id),
            'PUT domains/{id}' => $this->updateDomain($id, $body),
            'POST domains/{id}/renew' => $this->domainChange($id, 'domain.renewed'),
            'POST domains/{id}/restore' => $this->domainChange($id, 'domain.restored'),
            'DELETE domains/{id}' => $this->deleteDomain($id),
            'GET domains/{id}/authcode', 'POST domains/{id}/authcode/reset' => $this->authCode($id),
            default => throw new OpenproviderException("The Openprovider fake can't {$action}."),
        });
    }

    /**
     * @param  Fluent<array-key, mixed>  $query
     * @return array<string, mixed>
     */
    protected function listZones(Fluent $query): array
    {
        $names = Collection::make(array_keys($this->zones))
            ->filter(fn (string $name) => ! $query->filled('name_pattern') || Str::is($query->string('name_pattern')->value(), $name))
            ->filter(fn (string $name) => ! $query->filled('provider') || $this->zones[$name]['provider'] === $query->string('provider')->value());

        return $this->page($names->map(fn (string $name) => $this->zonePayload($name, false)), $query);
    }

    /**
     * @param  Fluent<array-key, mixed>  $body
     * @return array<string, mixed>
     */
    protected function createZone(Fluent $body): array
    {
        $name = (string) DomainName::fromArray($body->array('domain'));

        $this->zones[$name] = [
            'type' => $body->string('type', ZoneType::MASTER->value)->value(),
            'provider' => $body->string('provider')->value() ?: null,
            'records' => array_map(fn (array $record) => $this->stored($name, $record), $this->payloads($body, 'records')),
        ];

        $this->recordChange('zone.created', $name);

        return ['success' => true];
    }

    /**
     * @return array<string, mixed>
     */
    protected function zonePayload(string $name, bool $withRecords): array
    {
        $zone = $this->zones[$name] ?? throw new RequestFailed("Zone `{$name}` doesn't exist in the Openprovider fake.", 404);

        return [
            'id' => array_search($name, array_keys($this->zones), true) + 1,
            'name' => $name,
            'type' => $zone['type'],
            'active' => true,
            'provider' => $zone['provider'],
            'records' => $withRecords ? $zone['records'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function deleteZone(string $name): array
    {
        $this->zonePayload($name, false);

        unset($this->zones[$name]);
        $this->recordChange('zone.deleted', $name);

        return ['success' => true];
    }

    /**
     * @param  Fluent<array-key, mixed>  $body
     * @return array<string, mixed>
     */
    protected function changeRecords(string $zone, Fluent $body): array
    {
        $this->zonePayload($zone, false);
        $records = $this->zones[$zone]['records'];

        foreach ($this->payloads($body, 'records.add') as $record) {
            $records[] = $this->withSharedTtl($this->stored($zone, $record), $records);
            $this->recordChange('record.added', $zone, Record::fromArray($record));
        }

        foreach ($this->payloads($body, 'records.update') as $update) {
            $update = new Fluent($update);
            $original = $this->stored($zone, $update->array('original_record'));
            $replacement = $this->stored($zone, $update->array('record'));
            $records = array_map(fn (array $existing) => $this->matches($existing, $original) ? $replacement : $existing, $records);
            $records = array_map(fn (array $existing) => $this->sameSet($existing, $replacement) ? ['ttl' => $replacement['ttl']] + $existing : $existing, $records);
            $this->recordChange('record.updated', $zone, Record::fromArray($update->array('original_record')), Record::fromArray($update->array('record')));
        }

        foreach ($this->payloads($body, 'records.remove') as $record) {
            $stored = $this->stored($zone, $record);
            $records = array_values(array_filter($records, fn (array $existing) => ! $this->matches($existing, $stored)));
            $this->recordChange('record.deleted', $zone, Record::fromArray($record));
        }

        $this->zones[$zone]['records'] = $records;

        return ['success' => true];
    }

    /**
     * @param  Fluent<array-key, mixed>  $query
     * @return array<string, mixed>
     */
    protected function listRecords(string $zone, Fluent $query): array
    {
        $this->zonePayload($zone, false);

        $records = Collection::make($this->zones[$zone]['records'])
            ->filter(fn (array $record) => ! $query->filled('type') || $record['type'] === $query->string('type')->value());

        return $this->page($records, $query);
    }

    /**
     * @param  Fluent<array-key, mixed>  $query
     * @return array<string, mixed>
     */
    protected function listDomains(Fluent $query): array
    {
        $domains = Collection::make($this->domains)
            ->filter(fn (array $domain) => ! $query->filled('full_name') || $this->nameOf($domain) === $query->string('full_name')->value())
            ->filter(fn (array $domain) => ! $query->filled('domain_name_pattern') || Str::is($query->string('domain_name_pattern')->value(), (new Fluent($domain))->string('domain.name')->value()))
            ->filter(fn (array $domain) => ! $query->filled('status') || (new Fluent($domain))->string('status')->value() === $query->string('status')->value());

        return $this->page($domains, $query);
    }

    /**
     * @param  Fluent<array-key, mixed>  $body
     * @return array<string, mixed>
     */
    protected function addDomain(string $action, Fluent $body): array
    {
        $id = $this->nextDomainId();
        $this->domains[$id] = ['id' => $id, 'status' => 'ACT'] + Arr::except($body->toArray(), ['auth_code']);

        $this->recordChange($action, $this->nameOf($this->domains[$id]));

        return ['id' => $id, 'status' => 'ACT'];
    }

    /**
     * @param  Fluent<array-key, mixed>  $body
     * @return array<string, mixed>
     */
    protected function checkDomains(Fluent $body): array
    {
        $taken = Collection::make($this->domains)->map($this->nameOf(...));

        return ['results' => array_map(function (array $domain) use ($taken) {
            $name = (string) DomainName::fromArray($domain);

            return ['domain' => $name, 'status' => $taken->contains($name) ? 'in use' : 'free'];
        }, $this->payloads($body, 'domains'))];
    }

    /**
     * @return array<array-key, mixed>
     */
    protected function domainPayload(int $id): array
    {
        return $this->domains[$id] ?? throw new RequestFailed("Domain {$id} doesn't exist in the Openprovider fake.", 404);
    }

    /**
     * @param  Fluent<array-key, mixed>  $body
     * @return array<string, mixed>
     */
    protected function updateDomain(int $id, Fluent $body): array
    {
        $this->domains[$id] = $body->toArray() + $this->domainPayload($id);
        $this->recordChange('domain.updated', $id, $body->toArray());

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function domainChange(int $id, string $action): array
    {
        $this->domainPayload($id);
        $this->recordChange($action, $id);

        return [];
    }

    /**
     * @return array{auth_code: string}
     */
    protected function authCode(int $id): array
    {
        $this->domainPayload($id);

        return ['auth_code' => self::AUTH_CODE];
    }

    /**
     * @return array<string, mixed>
     */
    protected function deleteDomain(int $id): array
    {
        $this->domainPayload($id);

        unset($this->domains[$id]);
        $this->recordChange('domain.deleted', $id);

        return [];
    }

    /**
     * A record the way Openprovider stores it in `$zone`, as seen on production:
     * under its full name (`www` becomes `www.example.com`, an empty name the zone
     * name itself) and with a TXT value quoted. A name in a request is always relative
     * to the zone, so a full name matches nothing in an update or delete, as on
     * production.
     *
     * @param  array<array-key, mixed>  $record
     * @return array<string, mixed>
     */
    protected function stored(string $zone, array $record): array
    {
        $record = new Fluent($record);
        $name = $record->string('name')->value();
        $value = $record->string('value')->value();
        $isQuoted = Str::length($value) > 1 && Str::startsWith($value, '"') && Str::endsWith($value, '"');

        return Arr::whereNotNull([
            'name' => $name === '' ? $zone : "{$name}.{$zone}",
            'type' => $record->string('type')->value(),
            'value' => $record->string('type')->value() === RecordType::TXT->value && ! $isQuoted ? Str::wrap($value, '"') : $value,
            'ttl' => $record->integer('ttl'),
            'prio' => $record->filled('prio') ? $record->integer('prio') : null,
            'creation_date' => '',
            'modification_date' => '',
        ]);
    }

    /**
     * Records with the same name and type share one TTL, as on production: a new
     * record takes the TTL the others already have, and updating one record's TTL
     * changes it for all of them.
     *
     * @param  array<string, mixed>  $record
     * @param  list<array<string, mixed>>  $records
     * @return array<string, mixed>
     */
    protected function withSharedTtl(array $record, array $records): array
    {
        $sibling = Arr::first($records, fn (array $existing) => $this->sameSet($existing, $record));

        return $sibling === null ? $record : ['ttl' => $sibling['ttl']] + $record;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $other
     */
    protected function sameSet(array $record, array $other): bool
    {
        return $record['name'] === $other['name'] && $record['type'] === $other['type'];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $other
     */
    protected function matches(array $record, array $other): bool
    {
        $keys = ['name', 'type', 'value', 'ttl', 'prio'];

        return Arr::only($record, $keys) == Arr::only($other, $keys);
    }

    /**
     * @template TItem
     *
     * @param  Collection<array-key, TItem>  $items
     * @param  Fluent<array-key, mixed>  $query
     * @return array{results: list<TItem>, total: int}
     */
    protected function page(Collection $items, Fluent $query): array
    {
        return [
            'results' => array_values($items->values()->slice($query->integer('offset'), $query->integer('limit', 100))->all()),
            'total' => $items->count(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $domain
     */
    protected function nameOf(array $domain): string
    {
        return (string) DomainName::fromArray((new Fluent($domain))->array('domain'));
    }

    /**
     * The payloads under `$key`, skipping anything that isn't one.
     *
     * @param  Fluent<array-key, mixed>  $data
     * @return list<array<array-key, mixed>>
     */
    protected function payloads(Fluent $data, string $key): array
    {
        return array_values(array_filter($data->array($key), is_array(...)));
    }

    protected function nextDomainId(): int
    {
        return $this->domains === [] ? 1 : max(array_keys($this->domains)) + 1;
    }

    protected function recordChange(string $action, int|string $subject, mixed ...$arguments): void
    {
        $this->changes[] = ['action' => $action, 'subject' => $subject, 'arguments' => array_values($arguments)];
    }

    protected function assertChanged(string $action, int|string $subject, ?callable $callback, string $message): void
    {
        $matches = array_filter($this->changes, fn (array $change) => $change['action'] === $action
            && $change['subject'] === $subject
            && ($callback === null || $callback(...$change['arguments'])));

        PHPUnit::assertNotEmpty($matches, $message);
    }
}
