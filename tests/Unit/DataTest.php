<?php

declare(strict_types=1);

use SpitsOnline\Openprovider\Data\DomainName;
use SpitsOnline\Openprovider\Data\Page;
use SpitsOnline\Openprovider\Data\Record;
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
