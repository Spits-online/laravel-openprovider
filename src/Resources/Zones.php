<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Resources;

use Illuminate\Support\Arr;
use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Concerns\ConfirmsChanges;
use SpitsOnline\Openprovider\Concerns\Paginates;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\Zone;
use SpitsOnline\Openprovider\Enums\Provider;
use SpitsOnline\Openprovider\Enums\ZoneType;
use SpitsOnline\Openprovider\Openprovider;

/**
 * Every DNS zone in the account: `Openprovider::zones()`. To work with one zone, use
 * `Openprovider::zone('example.com')`.
 */
class Zones
{
    use ConfirmsChanges;
    use Paginates;

    /**
     * The largest page Openprovider returns for zones.
     */
    protected const int PAGE_SIZE = 500;

    public function __construct(
        protected Openprovider $openprovider,
    ) {}

    /**
     * Every zone, without its records, fetched a page at a time as you iterate.
     *
     * @param  ?string  $namePattern  e.g. `example*`; `*` is a wildcard
     * @return LazyCollection<int, Zone>
     */
    public function get(?string $namePattern = null, ?Provider $provider = null): LazyCollection
    {
        return $this->paginate(function (int $offset) use ($namePattern, $provider): array {
            $data = $this->openprovider->request('get', 'dns/zones', 'list the zones', Arr::whereNotNull([
                'limit' => self::PAGE_SIZE,
                'offset' => $offset,
                'name_pattern' => $namePattern,
                'provider' => $provider?->value,
            ]));

            return [
                $data->collect('results')
                    ->filter(fn (mixed $zone) => is_array($zone))
                    ->map(fn (array $zone) => Zone::fromArray($zone, $this->openprovider))
                    ->values()
                    ->all(),
                $data->integer('total'),
            ];
        });
    }

    /**
     * Create a zone that Openprovider serves, with its first records.
     *
     * @param  list<Record>  $records
     * @param  ?string  $template  the name of a DNS template in the Openprovider account
     */
    public function create(
        DomainName|string $name,
        array $records = [],
        bool $dnssec = false,
        ?string $template = null,
        ?Provider $provider = null,
    ): void {
        $name = DomainName::parse($name);

        $this->change('post', 'dns/zones', "create zone `{$name}`", body: [
            'domain' => $name->toArray(),
            'type' => ZoneType::MASTER->value,
            'records' => array_map(fn (Record $record) => $record->toArray(), $records) ?: null,
            'secured' => $dnssec ?: null,
            'template_name' => $template,
            'provider' => $provider?->value,
        ]);
    }

    /**
     * Create a slave zone, which copies its records from your own master server.
     */
    public function createSlave(DomainName|string $name, string $masterIp): void
    {
        $name = DomainName::parse($name);

        $this->change('post', 'dns/zones', "create zone `{$name}`", body: [
            'domain' => $name->toArray(),
            'type' => ZoneType::SLAVE->value,
            'master_ip' => $masterIp,
        ]);
    }
}
