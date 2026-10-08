<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Fluent;
use SpitsOnline\Openprovider\Exceptions\InvalidDomainName;
use Stringable;

/**
 * A domain split the way Openprovider wants it: `example.co.uk` is the name
 * `example` with the extension `co.uk`.
 */
final readonly class DomainName implements Stringable
{
    public function __construct(
        public string $name,
        public string $extension,
    ) {}

    public static function parse(self|string $domain): self
    {
        if ($domain instanceof self) {
            return $domain;
        }

        [$name, $extension] = array_pad(explode('.', trim($domain), 2), 2, '');

        if ($name === '' || $extension === '') {
            throw InvalidDomainName::from($domain);
        }

        return new self($name, $extension);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = new Fluent($payload);

        return new self($data->string('name')->value(), $data->string('extension')->value());
    }

    /**
     * @return array{name: string, extension: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'extension' => $this->extension];
    }

    public function __toString(): string
    {
        return "{$this->name}.{$this->extension}";
    }
}
