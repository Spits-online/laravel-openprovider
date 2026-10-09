<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Data;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use SpitsOnline\Openprovider\Enums\Provider;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Openprovider;
use SpitsOnline\Openprovider\Resources\ZoneRecords;

/**
 * A record read from a zone. It knows its zone, so it can update or delete itself:
 *
 *     $record->update(Record::create(RecordType::A, '5.6.7.8', 'www'));
 *     $record->delete();
 *
 * Openprovider returns records under their full name (`www.example.com`), and TXT
 * values wrapped in quotes.
 */
final readonly class ZoneRecord
{
    /**
     * @param  array<array-key, mixed>  $raw
     */
    public function __construct(
        public RecordType $type,
        public string $name,
        public string $value,
        public int $ttl,
        public ?int $priority,
        public string $zone,
        public ?Provider $provider,
        public array $raw,
        private Openprovider $openprovider,
    ) {}

    /**
     * @param  array<array-key, mixed>  $payload
     *
     * @internal
     */
    public static function fromArray(array $payload, string $zone, ?Provider $provider, Openprovider $openprovider): self
    {
        $data = new Fluent($payload);

        return new self(
            type: RecordType::from($data->string('type')->value()),
            name: $data->string('name')->value(),
            value: $data->string('value')->value(),
            ttl: $data->integer('ttl'),
            priority: $data->filled('prio') ? $data->integer('prio') : null,
            zone: $zone,
            provider: $provider,
            raw: $payload,
            openprovider: $openprovider,
        );
    }

    /**
     * Replace this record with another one. One request.
     */
    public function update(Record $record): void
    {
        $this->records()->update($this, $record);
    }

    /**
     * Delete this record. One request. To delete several at once, pass them all to
     * `Openprovider::zone(...)->records()->delete()`.
     */
    public function delete(): void
    {
        $this->records()->delete($this);
    }

    /**
     * The record as a `Record`, with its name relative to the zone (`www`), so you can
     * compare it with records you built, or add it to another zone.
     */
    public function toRecord(): Record
    {
        return new Record($this->type, $this->value, self::relativeName($this->name, $this->zone), $this->ttl, $this->priority, $this->raw);
    }

    /**
     * `www.example.com` in zone `example.com` is `www`; `example.com` itself is ``.
     *
     * @internal
     */
    public static function relativeName(string $name, string $zone): string
    {
        return match (true) {
            $name === $zone => '',
            Str::endsWith($name, ".{$zone}") => Str::beforeLast($name, ".{$zone}"),
            default => $name,
        };
    }

    /**
     * Whether both records describe the same DNS entry, ignoring `$raw` and TXT quotes.
     */
    public function is(Record|self $record): bool
    {
        return $this->toRecord()->is($record);
    }

    /**
     * The record as Openprovider returned it, in Openprovider's keys, with its full name.
     *
     * @return array{name: string, type: string, value: string, ttl: int, prio?: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->value,
            'value' => $this->value,
            'ttl' => $this->ttl,
            ...($this->priority === null ? [] : ['prio' => $this->priority]),
        ];
    }

    /**
     * The client is left out, so a queued job never stores the Openprovider password.
     * An unserialized record acts through the configured account.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'value' => $this->value,
            'ttl' => $this->ttl,
            'priority' => $this->priority,
            'zone' => $this->zone,
            'provider' => $this->provider,
            'raw' => $this->raw,
        ];
    }

    /**
     * @param  array{type: RecordType, name: string, value: string, ttl: int, priority: ?int, zone: string, provider: ?Provider, raw: array<array-key, mixed>}  $data
     */
    public function __unserialize(array $data): void
    {
        $this->type = $data['type'];
        $this->name = $data['name'];
        $this->value = $data['value'];
        $this->ttl = $data['ttl'];
        $this->priority = $data['priority'];
        $this->zone = $data['zone'];
        $this->provider = $data['provider'];
        $this->raw = $data['raw'];
        $this->openprovider = App::make(Openprovider::class);
    }

    private function records(): ZoneRecords
    {
        $zone = $this->openprovider->zone($this->zone);

        return ($this->provider === null ? $zone : $zone->provider($this->provider))->records();
    }
}
