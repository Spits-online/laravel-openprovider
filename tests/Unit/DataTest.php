<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SpitsOnline\Openprovider\Data\Domain;
use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Page;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\Zone;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Exceptions\InvalidDomainName;

it('builds a record with Openprovider\'s smallest TTL by default', function () {
    expect(Record::create(type: RecordType::A, value: '1.2.3.4')->toArray())
        ->toBe(['type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900]);
});

it('leaves an empty name and a missing priority out of the payload', function () {
    expect(Record::create(RecordType::MX, 'mail.example.com', 'mail', Ttl::DAY, 10)->toArray())
        ->toBe(['name' => 'mail', 'type' => 'MX', 'value' => 'mail.example.com', 'ttl' => 86400, 'prio' => 10]);
});

it('reads a record as Openprovider sends it', function () {
    $record = Record::fromArray(['name' => 'www.example.com', 'type' => 'CNAME', 'value' => 'example.com', 'ttl' => '3600', 'ip' => '127.0.0.1']);

    expect($record)
        ->type->toBe(RecordType::CNAME)
        ->ttl->toBe(3600)
        ->prio->toBeNull()
        ->raw->toHaveKey('ip', '127.0.0.1');
});

it('compares records by what they describe, not by their payload', function () {
    $read = Record::fromArray(['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900, 'creation_date' => '']);

    expect($read->is(Record::create(RecordType::A, '1.2.3.4', 'www')))->toBeTrue()
        ->and($read->is(Record::create(RecordType::A, '1.2.3.4', 'www', Ttl::HOUR)))->toBeFalse();
});

it('only treats SOA records as not editable', function () {
    expect(array_filter(RecordType::cases(), fn (RecordType $type) => ! $type->isEditable()))
        ->toBe([6 => RecordType::SOA]);
});

it('splits a domain at its first dot', function (string $domain, string $name, string $extension) {
    expect(DomainName::parse($domain))
        ->name->toBe($name)
        ->extension->toBe($extension)
        ->and((string) DomainName::parse($domain))->toBe($domain);
})->with([
    ['example.com', 'example', 'com'],
    ['example.co.uk', 'example', 'co.uk'],
]);

it('passes a parsed domain name through', function () {
    $name = new DomainName('example', 'com');

    expect(DomainName::parse($name))->toBe($name);
});

it('rejects text that is not a domain', function (string $domain) {
    expect(fn () => DomainName::parse($domain))->toThrow(InvalidDomainName::class);
})->with(['localhost', '.com', 'example.', '']);

it('knows whether more pages follow', function () {
    expect((new Page([1, 2], total: 5, limit: 2, offset: 0))->hasMore())->toBeTrue()
        ->and((new Page([5], total: 5, limit: 2, offset: 4))->hasMore())->toBeFalse();
});

it('reads dates as immutable Carbon instances in the app timezone', function () {
    config()->set('app.timezone', 'UTC');
    date_default_timezone_set('UTC');

    $zone = Zone::fromArray(['id' => 1, 'name' => 'example.com', 'creation_date' => '2019-06-27 06:22:36', 'modification_date' => '']);

    expect($zone->createdAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($zone->createdAt->toIso8601String())->toBe('2019-06-27T06:22:36+00:00')
        ->and($zone->modifiedAt)->toBeNull();
});

it('reads dates in openprovider.timezone when it is set', function () {
    config()->set('openprovider.timezone', 'Europe/Amsterdam');

    $domain = Domain::fromArray(['id' => 1, 'domain' => ['name' => 'example', 'extension' => 'com'], 'expiration_date' => '2022-09-27 07:03:04']);

    expect($domain->expirationDate->toIso8601String())->toBe('2022-09-27T07:03:04+02:00')
        ->and($domain->renewalDate)->toBeNull();
});

it('writes dates back in Openprovider\'s format', function () {
    config()->set('openprovider.timezone', 'Europe/Amsterdam');

    $zone = Zone::fromArray(['id' => 1, 'name' => 'example.com', 'creation_date' => '2019-06-27 06:22:36']);

    expect($zone->toArray())->toMatchArray(['creation_date' => '2019-06-27 06:22:36', 'modification_date' => null]);
});
