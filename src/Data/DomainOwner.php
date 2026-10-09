<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;

/**
 * Who owns a domain, as Openprovider shows it. The owner's contact handle is
 * `Domain::$ownerHandle`.
 */
final readonly class DomainOwner
{
    public function __construct(
        public ?string $fullName,
        public ?string $companyName,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self(
            fullName: $data->string('full_name')->value() ?: null,
            companyName: $data->string('company_name')->value() ?: null,
        );
    }
}
