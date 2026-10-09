<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Concerns\Paginates;
use SpitsOnline\Openprovider\Data\Domain;
use SpitsOnline\Openprovider\Data\DomainCheck;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Nameserver;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Openprovider;

/**
 * Every domain in the account: `Openprovider::domains()`. To work with one domain,
 * use `Openprovider::domain($id)`, or the `Domain` that `find()` returns.
 *
 * Contact handles (`$owner` and the others) are Openprovider customer handles, e.g.
 * `CV904717-NL`. `$attributes` takes any other field Openprovider documents for the request.
 */
class Domains
{
    use Paginates;

    protected const int PAGE_SIZE = 100;

    public function __construct(
        protected Openprovider $openprovider,
    ) {}

    /**
     * Every domain, fetched a page at a time as you iterate.
     *
     * @param  ?string  $pattern  matches the name without its extension; `*` is a wildcard
     * @param  ?string  $status  a status code, e.g. `ACT` (active) or `REQ` (requested)
     * @return LazyCollection<int, Domain>
     */
    public function get(?string $pattern = null, ?string $status = null): LazyCollection
    {
        return $this->paginate(function (int $offset) use ($pattern, $status): array {
            $data = $this->openprovider->request('get', 'domains', 'list the domains', Arr::whereNotNull([
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'domain_name_pattern' => $pattern,
                'status' => $status,
            ]));

            return [$data->array('results'), $data->integer('total')];
        })
            ->filter(fn (mixed $domain) => is_array($domain))
            ->map(fn (array $domain) => Domain::fromArray($domain, $this->openprovider))
            ->values();
    }

    /**
     * The domain with this name, or null when it isn't in the account. One request:
     * Openprovider addresses domains by id, so a name has to be looked up.
     */
    public function find(DomainName|string $name): ?Domain
    {
        $name = (string) DomainName::parse($name);

        $data = $this->openprovider->request('get', 'domains', "find domain `{$name}`", [
            'full_name' => $name,
            'limit' => 1,
        ]);

        $domain = Arr::first($data->array('results'));

        return is_array($domain) ? Domain::fromArray($domain, $this->openprovider) : null;
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
    public function register(
        DomainName|string $name,
        string $owner,
        ?string $admin = null,
        ?string $tech = null,
        ?string $billing = null,
        int $period = 1,
        array $nameServers = [],
        ?string $nsGroup = null,
        ?Autorenew $autorenew = null,
        array $attributes = [],
    ): Domain {
        $name = DomainName::parse($name);

        $data = $this->openprovider->request('post', 'domains', "register `{$name}`", body: Arr::whereNotNull([
            'domain' => $name->toArray(),
            'owner_handle' => $owner,
            'admin_handle' => $admin,
            'tech_handle' => $tech,
            'billing_handle' => $billing,
            'period' => $period,
            'name_servers' => self::nameServers($nameServers),
            'ns_group' => $nsGroup,
            'autorenew' => $autorenew?->value,
        ]) + $attributes);

        return Domain::fromArray(['domain' => $name->toArray()] + $data->toArray(), $this->openprovider);
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
        string $owner,
        ?string $admin = null,
        ?string $tech = null,
        ?string $billing = null,
        array $nameServers = [],
        ?string $nsGroup = null,
        ?Autorenew $autorenew = null,
        array $attributes = [],
    ): Domain {
        $name = DomainName::parse($name);

        $data = $this->openprovider->request('post', 'domains/transfer', "transfer `{$name}`", body: Arr::whereNotNull([
            'domain' => $name->toArray(),
            'auth_code' => $authCode,
            'owner_handle' => $owner,
            'admin_handle' => $admin,
            'tech_handle' => $tech,
            'billing_handle' => $billing,
            'name_servers' => self::nameServers($nameServers),
            'ns_group' => $nsGroup,
            'autorenew' => $autorenew?->value,
        ]) + $attributes);

        return Domain::fromArray(['domain' => $name->toArray()] + $data->toArray(), $this->openprovider);
    }

    /**
     * @param  list<Nameserver|string>  $nameServers
     * @return ?list<array<string, string>>
     *
     * @internal
     */
    public static function nameServers(array $nameServers): ?array
    {
        return array_map(fn (Nameserver|string $nameserver) => Nameserver::from($nameserver)->toArray(), $nameServers) ?: null;
    }
}
