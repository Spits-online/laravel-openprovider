<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Resources;

use Illuminate\Support\Arr;
use SpitsOnline\Openprovider\Concerns\ConfirmsChanges;
use SpitsOnline\Openprovider\Data\Zone;
use SpitsOnline\Openprovider\Enums\Provider;
use SpitsOnline\Openprovider\Openprovider;

/**
 * One DNS zone: `Openprovider::zone('example.com')`. Picking a zone sends no request;
 * every method after it sends exactly one.
 */
class ZoneResource
{
    use ConfirmsChanges;

    final public function __construct(
        protected Openprovider $openprovider,
        protected string $name,
        protected ?Provider $provider = null,
    ) {}

    /**
     * The same zone at a premium DNS provider, e.g. `->provider(Provider::SECTIGO)`.
     */
    public function provider(Provider $provider): static
    {
        return new static($this->openprovider, $this->name, $provider);
    }

    /**
     * The zone with its records.
     */
    public function get(): Zone
    {
        $data = $this->openprovider->request('get', $this->path(), "find zone `{$this->name}`", Arr::whereNotNull([
            'with_records' => true,
            'provider' => $this->provider?->value,
        ]));

        return Zone::fromArray($data->toArray(), $this->openprovider);
    }

    /**
     * Delete the zone and its records. Openprovider can't restore a deleted zone.
     */
    public function delete(): void
    {
        $this->change('delete', $this->path(), "delete zone `{$this->name}`", query: [
            'provider' => $this->provider?->value,
        ]);
    }

    /**
     * The zone's records, to read, add, update and delete.
     */
    public function records(): ZoneRecords
    {
        return new ZoneRecords($this->openprovider, $this->name, $this->provider);
    }

    protected function path(): string
    {
        return 'dns/zones/'.rawurlencode($this->name);
    }
}
