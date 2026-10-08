<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Enums\ZoneType;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Facades\Openprovider;

const ZONE = OPENPROVIDER.'/dns/zones/demo-domain.nl';

/**
 * @return array<string, mixed>
 */
function recordsPage(int $total, string ...$values): array
{
    return ['code' => 0, 'data' => [
        'results' => array_map(fn (string $value) => ['name' => 'www', 'type' => 'A', 'value' => $value, 'ttl' => 900], $values),
        'total' => $total,
    ]];
}

it('finds a zone with its records', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    $zone = Openprovider::zones()->find('demo-domain.nl');

    expect($zone)
        ->id->toBe(9146574)
        ->name->toBe('demo-domain.nl')
        ->type->toBe(ZoneType::MASTER)
        ->isActive->toBeTrue()
        ->provider->toBeNull()
        ->createdAt->toBe('2019-06-27 06:22:36')
        ->records->toHaveCount(2)
        ->and($zone->records[1])
        ->type->toBe(RecordType::MX)
        ->name->toBe('demo-domain.nl')
        ->value->toBe('mail.demo-domain.nl')
        ->ttl->toBe(86400)
        ->prio->toBe(10)
        ->raw->toHaveKey('ip', '127.0.0.1');

    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'?with_records=true');
});

it('finds a premium zone without its records', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zones()->find('demo-domain.nl', withRecords: false, provider: 'sectigo');

    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'?with_records=false&provider=sectigo');
});

it('lists a page of zones', function () {
    fakeOpenprovider(['dns/zones*' => Http::response(openproviderFixture('zones'))]);

    $page = Openprovider::zones()->list(limit: 10, offset: 20, namePattern: 'demo*');

    expect($page)
        ->total->toBe(1)
        ->limit->toBe(10)
        ->offset->toBe(20)
        ->and($page->items[0]->name)->toBe('demo-domain.nl');

    Http::assertSent(fn (Request $request) => $request->url() === OPENPROVIDER.'/dns/zones?limit=10&offset=20&name_pattern=demo%2A&with_records=false');
});

it('walks every zone lazily', function () {
    fakeOpenprovider(['dns/zones*' => Http::response(openproviderFixture('zones'))]);

    $zones = Openprovider::zones()->all();

    expect($zones)->toBeInstanceOf(LazyCollection::class);
    Http::assertNothingSent();

    expect($zones->all())->toHaveCount(1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'limit=500&offset=0'));
});

it('walks every record of a zone, a page at a time', function () {
    fakeOpenprovider(['dns/zones/demo-domain.nl/records*' => Http::sequence()
        ->push(recordsPage(3, '1.1.1.1', '2.2.2.2'))
        ->push(recordsPage(3, '3.3.3.3'))]);

    $values = Openprovider::zones()->records('demo-domain.nl', RecordType::A)
        ->map(fn (Record $record) => $record->value)
        ->all();

    expect($values)->toBe(['1.1.1.1', '2.2.2.2', '3.3.3.3']);

    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'/records?limit=500&offset=0&type=A');
    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'/records?limit=500&offset=2&type=A');
    Http::assertSentCount(3);
});

it('stops walking at an empty page', function () {
    fakeOpenprovider(['dns/zones/demo-domain.nl/records*' => Http::response(recordsPage(10))]);

    expect(Openprovider::zones()->records('demo-domain.nl')->all())->toBe([]);

    Http::assertSentCount(2);
});

it('creates a master zone with records', function () {
    fakeOpenprovider(['dns/zones' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->create('demo-domain.nl', [
        Record::create(type: RecordType::A, value: '1.2.3.4', name: 'www'),
    ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === OPENPROVIDER.'/dns/zones'
        && $request->data() === [
            'domain' => ['name' => 'demo-domain', 'extension' => 'nl'],
            'type' => 'master',
            'records' => [['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900]],
        ]);
});

it('creates a slave zone, with DNSSEC, a template and a provider', function () {
    fakeOpenprovider(['dns/zones' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->create('demo-domain.co.uk', masterIp: '192.0.2.1', isDnssecEnabled: true, template: 'default', provider: 'sectigo');

    Http::assertSent(fn (Request $request) => $request->data() === [
        'domain' => ['name' => 'demo-domain', 'extension' => 'co.uk'],
        'type' => 'slave',
        'master_ip' => '192.0.2.1',
        'secured' => true,
        'template_name' => 'default',
        'provider' => 'sectigo',
    ]);
});

it('deletes a zone', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->delete('demo-domain.nl', provider: 'sectigo');

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === ZONE.'?provider=sectigo');
});

it('adds records', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->addRecords('demo-domain.nl', [
        Record::create(type: RecordType::A, value: '1.2.3.4'),
        Record::create(type: RecordType::MX, value: 'mail.demo-domain.nl', ttl: Ttl::HOUR, prio: 10),
    ]);

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === ZONE
        && $request->data() === ['records' => ['add' => [
            ['type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
            ['type' => 'MX', 'value' => 'mail.demo-domain.nl', 'ttl' => 3600, 'prio' => 10],
        ]]]);
});

it('updates a record', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->updateRecord(
        'demo-domain.nl',
        Record::create(type: RecordType::A, value: '1.2.3.4', name: 'www'),
        Record::create(type: RecordType::A, value: '5.6.7.8', name: 'www'),
        provider: 'sectigo',
    );

    Http::assertSent(fn (Request $request) => $request->data() === [
        'records' => ['update' => [[
            'original_record' => ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
            'record' => ['name' => 'www', 'type' => 'A', 'value' => '5.6.7.8', 'ttl' => 900],
        ]]],
        'provider' => 'sectigo',
    ]);
});

it('removes records exactly as Openprovider returned them', function () {
    fakeOpenprovider(['dns/zones/*' => Http::sequence()
        ->push(openproviderFixture('zone'))
        ->push(openproviderFixture('success'))]);

    $zone = Openprovider::zones()->find('demo-domain.nl');
    Openprovider::zones()->removeRecords('demo-domain.nl', $zone->records);

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data() === ['records' => ['remove' => [
        ['name' => 'www.demo-domain.nl', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
        ['name' => 'demo-domain.nl', 'type' => 'MX', 'value' => 'mail.demo-domain.nl', 'ttl' => 86400, 'prio' => 10],
    ]]]);
});

it('throws when Openprovider does not confirm a change', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(['code' => 0, 'data' => ['success' => false]])]);

    expect(fn () => Openprovider::zones()->addRecords('demo-domain.nl', [Record::create(RecordType::A, '1.2.3.4')]))
        ->toThrow(RequestFailed::class, 'Openprovider could not add records to `demo-domain.nl`: its answer has no `success: true`.');
});

it('url-encodes the zone name', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zones()->find('a/b.nl');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), OPENPROVIDER.'/dns/zones/a%2Fb.nl'));
});
