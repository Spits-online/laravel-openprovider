<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Concerns\ListsFromArray;

/**
 * Whether a domain can be registered. `$raw` holds the full result, including the
 * price when it was checked with one.
 */
final readonly class DomainCheck
{
    use ListsFromArray;

    /**
     * @param  string  $status  `free`, `reserved` or `in use`
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public string $domain,
        public string $status,
        public bool $isPremium,
        public array $raw,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self(
            domain: $data->string('domain')->value(),
            status: $data->string('status')->value(),
            isPremium: $data->boolean('is_premium'),
            raw: $payload,
        );
    }

    public function isAvailable(): bool
    {
        return $this->status === 'free';
    }
}
