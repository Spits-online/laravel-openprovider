<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Concerns\ListsFromArray;

/**
 * A nameserver of a domain, with its optional IPv4 and IPv6 address.
 */
final readonly class Nameserver
{
    use ListsFromArray;

    public function __construct(
        public string $name,
        public ?string $ip = null,
        public ?string $ip6 = null,
    ) {}

    public static function create(string $name, ?string $ip = null, ?string $ip6 = null): self
    {
        return new self($name, $ip, $ip6);
    }

    public static function from(self|string $nameserver): self
    {
        return $nameserver instanceof self ? $nameserver : new self($nameserver);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self(
            name: $data->string('name')->value(),
            ip: $data->string('ip')->value() ?: null,
            ip6: $data->string('ip6')->value() ?: null,
        );
    }

    /**
     * @return array{name: string, ip?: string, ip6?: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            ...($this->ip === null ? [] : ['ip' => $this->ip]),
            ...($this->ip6 === null ? [] : ['ip6' => $this->ip6]),
        ];
    }
}
