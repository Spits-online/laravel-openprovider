<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Testing;

use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert as PHPUnit;
use SpitsOnline\Openprovider\Data\Domain;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Exceptions\OpenproviderException;
use SpitsOnline\Openprovider\Openprovider;
use SpitsOnline\Openprovider\Resources\Domains;
use SpitsOnline\Openprovider\Resources\Zones;

/**
 * An in-memory Openprovider for testing apps. Seed it with `withZone()` and
 * `withDomain()`; changes apply to the seeded data and can be asserted. Enable it
 * with `Openprovider::fake()`.
 */
class OpenproviderFake extends Openprovider
{
    /** @var array<string, list<Record>> */
    protected array $zones = [];

    /** @var array<int, Domain> */
    protected array $domains = [];

    /** @var list<array{action: string, subject: int|string, arguments: list<mixed>}> */
    protected array $changes = [];

    public function __construct()
    {
        parent::__construct('fake', 'fake', null, 'https://openprovider.test');
    }

    /**
     * Seed a zone. Its records are stored the way Openprovider stores them; see `stored()`.
     *
     * @param  list<Record>  $records
     */
    public function withZone(string $name, array $records = []): static
    {
        $this->putZone($name, $records);

        return $this;
    }

    public function withDomain(Domain|string $domain): static
    {
        $domain = $domain instanceof Domain ? $domain : Domain::fromArray([
            'id' => $this->nextDomainId(),
            'domain' => DomainName::parse($domain)->toArray(),
            'status' => 'ACT',
        ]);

        $this->domains[$domain->id] = $domain;

        return $this;
    }

    public function zones(): Zones
    {
        return new FakeZones($this);
    }

    public function domains(): Domains
    {
        return new FakeDomains($this);
    }

    public function request(string $method, string $path, string $action, array $query = [], array $body = []): Fluent
    {
        throw new OpenproviderException("The Openprovider fake can't {$action}: it only answers through zones() and domains().");
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
     * @param  (callable(Record): bool)|null  $callback
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
     * @param  (callable(Record): bool)|null  $callback
     */
    public function assertRecordRemoved(string $zone, ?callable $callback = null): void
    {
        $this->assertChanged('record.removed', $zone, $callback, "No matching record was removed from `{$zone}`.");
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
     * @param  (callable(array<string, mixed> $changes): bool)|null  $callback  receives the arguments passed to `update()`
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
     * @internal
     *
     * @return array<string, list<Record>>
     */
    public function zoneStore(): array
    {
        return $this->zones;
    }

    /**
     * @internal
     *
     * @param  list<Record>  $records
     */
    public function putZone(string $name, array $records): void
    {
        $this->zones[$name] = array_map(fn (Record $record) => $this->stored($name, $record), $records);
    }

    /**
     * @internal
     *
     * A record the way Openprovider stores it in `$zone`, as seen on production:
     * under its full name (`www` becomes `www.example.com`, an empty name the zone
     * name itself) and with a TXT value quoted.
     */
    public function stored(string $zone, Record $record): Record
    {
        $record = $record->stored();

        $name = match (true) {
            $record->name === '' => $zone,
            $record->name === $zone, Str::endsWith($record->name, ".{$zone}") => $record->name,
            default => "{$record->name}.{$zone}",
        };

        return new Record($record->type, $record->value, $name, $record->ttl, $record->prio, $record->raw);
    }

    /**
     * @internal
     */
    public function forgetZone(string $name): void
    {
        unset($this->zones[$name]);
    }

    /**
     * @internal
     *
     * @return array<int, Domain>
     */
    public function domainStore(): array
    {
        return $this->domains;
    }

    /**
     * @internal
     */
    public function forgetDomain(int $id): void
    {
        unset($this->domains[$id]);
    }

    /**
     * @internal
     */
    public function nextDomainId(): int
    {
        return $this->domains === [] ? 1 : max(array_keys($this->domains)) + 1;
    }

    /**
     * @internal
     */
    public function recordChange(string $action, int|string $subject, mixed ...$arguments): void
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
