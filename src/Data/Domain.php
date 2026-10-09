<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Concerns\ReadsDates;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Openprovider;
use SpitsOnline\Openprovider\Resources\DomainResource;

/**
 * A domain as Openprovider returns it. It knows its id, so it can act on itself
 * with one request each: `$domain->renew()`, `$domain->lock()` and the others, the
 * same as `Openprovider::domain($id)`. Dates are read in `openprovider.timezone`
 * (see `ReadsDates`); `$raw` holds the full payload.
 */
final readonly class Domain
{
    use ReadsDates;

    /**
     * @param  ?string  $status  Openprovider's status code, e.g. `ACT` (active) or `REQ` (requested)
     * @param  list<Nameserver>  $nameServers
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public int $id,
        public DomainName $name,
        public ?string $status,
        public ?Autorenew $autorenew,
        public array $nameServers,
        public bool $isLocked,
        public bool $isPrivateWhoisEnabled,
        public ?string $ownerHandle,
        public ?string $adminHandle,
        public ?string $techHandle,
        public ?string $billingHandle,
        public ?CarbonInterface $expirationDate,
        public ?CarbonInterface $renewalDate,
        public array $raw,
        private Openprovider $openprovider,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @internal
     */
    public static function fromArray(array $payload, Openprovider $openprovider): self
    {
        $data = new Fluent($payload);

        return new self(
            id: $data->integer('id'),
            name: DomainName::fromArray($data->array('domain')),
            status: $data->string('status')->value() ?: null,
            autorenew: $data->enum('autorenew', Autorenew::class),
            nameServers: Nameserver::listFrom($data->array('name_servers')),
            isLocked: $data->boolean('is_locked'),
            isPrivateWhoisEnabled: $data->boolean('is_private_whois_enabled'),
            ownerHandle: $data->string('owner_handle')->value() ?: null,
            adminHandle: $data->string('admin_handle')->value() ?: null,
            techHandle: $data->string('tech_handle')->value() ?: null,
            billingHandle: $data->string('billing_handle')->value() ?: null,
            expirationDate: self::date($data, 'expiration_date'),
            renewalDate: self::date($data, 'renewal_date'),
            raw: $payload,
            openprovider: $openprovider,
        );
    }

    /**
     * Change the domain. Only the arguments you pass are changed. See `DomainResource::update()`.
     *
     * @param  ?list<Nameserver|string>  $nameServers
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        ?array $nameServers = null,
        ?string $nsGroup = null,
        ?Autorenew $autorenew = null,
        ?bool $isLocked = null,
        ?bool $isPrivateWhoisEnabled = null,
        ?string $owner = null,
        ?string $admin = null,
        ?string $tech = null,
        ?string $billing = null,
        ?string $comments = null,
        array $attributes = [],
    ): void {
        $this->resource()->update($nameServers, $nsGroup, $autorenew, $isLocked, $isPrivateWhoisEnabled, $owner, $admin, $tech, $billing, $comments, $attributes);
    }

    /**
     * @param  int  $period  the number of renewals; a yearly domain is renewed a year each
     */
    public function renew(int $period = 1): void
    {
        $this->resource()->renew($period);
    }

    public function lock(): void
    {
        $this->resource()->lock();
    }

    public function unlock(): void
    {
        $this->resource()->unlock();
    }

    public function restore(): void
    {
        $this->resource()->restore();
    }

    public function delete(): void
    {
        $this->resource()->delete();
    }

    public function authCode(): string
    {
        return $this->resource()->authCode();
    }

    public function resetAuthCode(): string
    {
        return $this->resource()->resetAuthCode();
    }

    /**
     * The client is left out, so a queued job never stores the Openprovider password.
     * An unserialized domain acts through the configured account.
     *
     * @return array<array-key, mixed>
     */
    public function __serialize(): array
    {
        return array_diff_key(get_object_vars($this), ['openprovider' => true]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        foreach ($data as $property => $value) {
            $this->{$property} = $value;
        }

        $this->openprovider = App::make(Openprovider::class);
    }

    private function resource(): DomainResource
    {
        return $this->openprovider->domain($this->id);
    }
}
