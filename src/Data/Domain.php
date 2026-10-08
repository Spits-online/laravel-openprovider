<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Concerns\ListsFromArray;
use SpitsOnline\Openprovider\Enums\Autorenew;

/**
 * A domain as Openprovider returns it. Dates are kept exactly as Openprovider sends
 * them (`2026-05-01 12:00:00`); `$raw` holds the full payload.
 */
final readonly class Domain
{
    use ListsFromArray;

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
        public ?string $expirationDate,
        public ?string $renewalDate,
        public array $raw,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
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
            expirationDate: $data->string('expiration_date')->value() ?: null,
            renewalDate: $data->string('renewal_date')->value() ?: null,
            raw: $payload,
        );
    }
}
