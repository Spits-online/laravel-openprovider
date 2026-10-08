<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Testing;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SpitsOnline\Openprovider\Data\Domain;
use SpitsOnline\Openprovider\Data\DomainCheck;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Page;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Resources\Domains;

/**
 * The domains of `OpenproviderFake`. A domain that wasn't seeded, registered or
 * transferred answers like a missing one: with a `RequestFailed` exception with
 * status 404. Seeded domains are `in use` for `check()`; every other name is `free`.
 */
class FakeDomains extends Domains
{
    public const string AUTH_CODE = 'fake-auth-code';

    public function __construct(
        protected OpenproviderFake $fake,
    ) {
        parent::__construct($fake);
    }

    public function list(int $limit = 100, int $offset = 0, ?string $pattern = null, ?string $status = null): Page
    {
        $domains = Collection::make($this->fake->domainStore())
            ->filter(fn (Domain $domain) => ($pattern === null || Str::is($pattern, (string) $domain->name))
                && ($status === null || $domain->status === $status))
            ->values();

        return new Page(
            items: array_values($domains->slice($offset, $limit)->all()),
            total: $domains->count(),
            limit: $limit,
            offset: $offset,
        );
    }

    public function find(int $id): Domain
    {
        return $this->fake->domainStore()[$id] ?? throw new RequestFailed("Domain {$id} doesn't exist in the Openprovider fake.", 404);
    }

    public function findByName(DomainName|string $name): ?Domain
    {
        $name = (string) DomainName::parse($name);

        return Arr::first($this->fake->domainStore(), fn (Domain $domain) => (string) $domain->name === $name);
    }

    public function check(array $domains, bool $withPrice = false): array
    {
        return array_map(fn (DomainName|string $domain) => DomainCheck::fromArray([
            'domain' => (string) DomainName::parse($domain),
            'status' => $this->findByName($domain) === null ? 'free' : 'in use',
        ]), $domains);
    }

    public function create(
        DomainName|string $name,
        string $ownerHandle,
        ?string $adminHandle = null,
        ?string $techHandle = null,
        ?string $billingHandle = null,
        int $period = 1,
        array $nameServers = [],
        ?string $nsGroup = null,
        ?Autorenew $autorenew = null,
        array $attributes = [],
    ): Domain {
        return $this->add('domain.registered', DomainName::parse($name), $ownerHandle);
    }

    public function transfer(
        DomainName|string $name,
        string $authCode,
        string $ownerHandle,
        ?string $adminHandle = null,
        ?string $techHandle = null,
        ?string $billingHandle = null,
        array $nameServers = [],
        ?string $nsGroup = null,
        ?Autorenew $autorenew = null,
        array $attributes = [],
    ): Domain {
        return $this->add('domain.transferred', DomainName::parse($name), $ownerHandle);
    }

    public function update(
        int $id,
        ?array $nameServers = null,
        ?string $nsGroup = null,
        ?Autorenew $autorenew = null,
        ?bool $isLocked = null,
        ?bool $isPrivateWhoisEnabled = null,
        ?string $ownerHandle = null,
        ?string $adminHandle = null,
        ?string $techHandle = null,
        ?string $billingHandle = null,
        ?string $comments = null,
        array $attributes = [],
    ): void {
        $this->find($id);

        $this->fake->recordChange('domain.updated', $id, Arr::whereNotNull([
            'nameServers' => $nameServers,
            'nsGroup' => $nsGroup,
            'autorenew' => $autorenew,
            'isLocked' => $isLocked,
            'isPrivateWhoisEnabled' => $isPrivateWhoisEnabled,
            'ownerHandle' => $ownerHandle,
            'adminHandle' => $adminHandle,
            'techHandle' => $techHandle,
            'billingHandle' => $billingHandle,
            'comments' => $comments,
        ]) + $attributes);
    }

    public function renew(int $id, int $period = 1): void
    {
        $this->find($id);

        $this->fake->recordChange('domain.renewed', $id);
    }

    public function restore(int $id): void
    {
        $this->find($id);

        $this->fake->recordChange('domain.restored', $id);
    }

    public function delete(int $id): void
    {
        $this->find($id);

        $this->fake->forgetDomain($id);
        $this->fake->recordChange('domain.deleted', $id);
    }

    public function authCode(int $id): string
    {
        $this->find($id);

        return self::AUTH_CODE;
    }

    public function resetAuthCode(int $id): string
    {
        $this->find($id);

        return self::AUTH_CODE;
    }

    protected function add(string $action, DomainName $name, string $ownerHandle): Domain
    {
        $domain = Domain::fromArray([
            'id' => $this->fake->nextDomainId(),
            'domain' => $name->toArray(),
            'status' => 'ACT',
            'owner_handle' => $ownerHandle,
        ]);

        $this->fake->withDomain($domain);
        $this->fake->recordChange($action, (string) $name);

        return $domain;
    }
}
