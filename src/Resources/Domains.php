<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Concerns\Paginates;
use SpitsOnline\Openprovider\Data\Domain;
use SpitsOnline\Openprovider\Data\DomainCheck;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Nameserver;
use SpitsOnline\Openprovider\Data\Page;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Openprovider;

/**
 * The domains in the Openprovider account. Contact handles (`$ownerHandle` and the
 * others) are Openprovider customer handles, e.g. `CV904717-NL`.
 * `$attributes` takes any other field Openprovider documents for the request.
 */
class Domains
{
    use Paginates;

    protected const int PAGE_SIZE = 100;

    public function __construct(
        protected Openprovider $openprovider,
    ) {}

    /**
     * One page of domains. `$pattern` matches the name without its extension and
     * accepts `*` as a wildcard; `$status` is a status code such as `ACT` or `REQ`.
     *
     * @return Page<Domain>
     */
    public function list(int $limit = 100, int $offset = 0, ?string $pattern = null, ?string $status = null): Page
    {
        $data = $this->openprovider->request('get', 'domains', 'list the domains', Arr::whereNotNull([
            'limit' => $limit,
            'offset' => $offset,
            'domain_name_pattern' => $pattern,
            'status' => $status,
        ]));

        return new Page(
            items: Domain::listFrom($data->array('results')),
            total: $data->integer('total'),
            limit: $limit,
            offset: $offset,
        );
    }

    /**
     * Every domain, fetched a page at a time as you iterate.
     *
     * @return LazyCollection<int, Domain>
     */
    public function all(?string $pattern = null, ?string $status = null): LazyCollection
    {
        return $this->paginate(fn (int $offset) => $this->list(self::PAGE_SIZE, $offset, $pattern, $status));
    }

    public function find(int $id): Domain
    {
        return Domain::fromArray($this->openprovider->request('get', $this->path($id), "find domain {$id}")->toArray());
    }

    /**
     * The domain with this name, or null when it isn't in the account.
     */
    public function findByName(DomainName|string $name): ?Domain
    {
        $name = (string) DomainName::parse($name);

        $data = $this->openprovider->request('get', 'domains', "find domain `{$name}`", [
            'full_name' => $name,
            'limit' => 1,
        ]);

        return Domain::listFrom($data->array('results'))[0] ?? null;
    }

    /**
     * Whether domains can be registered, with their price when `$withPrice` is set.
     *
     * @param  list<DomainName|string>  $domains
     * @return list<DomainCheck>
     */
    public function check(array $domains, bool $withPrice = false): array
    {
        $data = $this->openprovider->request('post', 'domains/check', 'check the domains', body: Arr::whereNotNull([
            'domains' => array_map(fn (DomainName|string $domain) => DomainName::parse($domain)->toArray(), $domains),
            'with_price' => $withPrice ?: null,
        ]));

        return DomainCheck::listFrom($data->array('results'));
    }

    /**
     * Register a domain. Openprovider charges the account for it.
     *
     * @param  int  $period  the registration period, in years for most extensions
     * @param  list<Nameserver|string>  $nameServers
     * @param  array<string, mixed>  $attributes
     */
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
        $name = DomainName::parse($name);

        $data = $this->openprovider->request('post', 'domains', "register `{$name}`", body: Arr::whereNotNull([
            'domain' => $name->toArray(),
            'owner_handle' => $ownerHandle,
            'admin_handle' => $adminHandle,
            'tech_handle' => $techHandle,
            'billing_handle' => $billingHandle,
            'period' => $period,
            'name_servers' => $this->nameServers($nameServers),
            'ns_group' => $nsGroup,
            'autorenew' => $autorenew?->value,
        ]) + $attributes);

        return Domain::fromArray(['domain' => $name->toArray()] + $data->toArray());
    }

    /**
     * Transfer a domain into the account with its auth code.
     *
     * @param  list<Nameserver|string>  $nameServers
     * @param  array<string, mixed>  $attributes
     */
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
        $name = DomainName::parse($name);

        $data = $this->openprovider->request('post', 'domains/transfer', "transfer `{$name}`", body: Arr::whereNotNull([
            'domain' => $name->toArray(),
            'auth_code' => $authCode,
            'owner_handle' => $ownerHandle,
            'admin_handle' => $adminHandle,
            'tech_handle' => $techHandle,
            'billing_handle' => $billingHandle,
            'name_servers' => $this->nameServers($nameServers),
            'ns_group' => $nsGroup,
            'autorenew' => $autorenew?->value,
        ]) + $attributes);

        return Domain::fromArray(['domain' => $name->toArray()] + $data->toArray());
    }

    /**
     * Change a domain. Only the arguments you pass are changed.
     *
     * @param  ?list<Nameserver|string>  $nameServers
     * @param  array<string, mixed>  $attributes
     */
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
        $this->openprovider->request('put', $this->path($id), "update domain {$id}", body: Arr::whereNotNull([
            'name_servers' => $nameServers === null ? null : $this->nameServers($nameServers),
            'ns_group' => $nsGroup,
            'autorenew' => $autorenew?->value,
            'is_locked' => $isLocked,
            'is_private_whois_enabled' => $isPrivateWhoisEnabled,
            'owner_handle' => $ownerHandle,
            'admin_handle' => $adminHandle,
            'tech_handle' => $techHandle,
            'billing_handle' => $billingHandle,
            'comments' => $comments,
        ]) + $attributes);
    }

    /**
     * Renew a domain.
     *
     * @param  int  $period  the number of renewals; a yearly domain is renewed a year each
     */
    public function renew(int $id, int $period = 1): void
    {
        $this->openprovider->request('post', $this->path($id).'/renew', "renew domain {$id}", body: ['period' => $period]);
    }

    /**
     * Restore a deleted domain.
     */
    public function restore(int $id): void
    {
        $this->openprovider->request('post', $this->path($id).'/restore', "restore domain {$id}");
    }

    public function delete(int $id): void
    {
        $this->openprovider->request('delete', $this->path($id), "delete domain {$id}");
    }

    /**
     * The domain's transfer auth code.
     */
    public function authCode(int $id): string
    {
        $data = $this->openprovider->request('get', $this->path($id).'/authcode', "get the auth code of domain {$id}");

        return $this->authCodeFrom($data, "get the auth code of domain {$id}");
    }

    /**
     * Replace the domain's auth code with a new one, and return it.
     */
    public function resetAuthCode(int $id): string
    {
        $data = $this->openprovider->request('post', $this->path($id).'/authcode/reset', "reset the auth code of domain {$id}");

        return $this->authCodeFrom($data, "reset the auth code of domain {$id}");
    }

    /**
     * @param  Fluent<array-key, mixed>  $data
     */
    protected function authCodeFrom(Fluent $data, string $action): string
    {
        $authCode = $data->string('auth_code')->value();

        return $authCode !== '' ? $authCode : throw RequestFailed::unexpected($action, 'auth code', $data->toArray());
    }

    /**
     * @param  list<Nameserver|string>  $nameServers
     * @return ?list<array<string, string>>
     */
    protected function nameServers(array $nameServers): ?array
    {
        return array_map(fn (Nameserver|string $nameserver) => Nameserver::from($nameserver)->toArray(), $nameServers) ?: null;
    }

    protected function path(int $id): string
    {
        return "domains/{$id}";
    }
}
