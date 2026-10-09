<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\SoaRecord;
use SpitsOnline\Openprovider\Data\ZoneRecord;
use SpitsOnline\Openprovider\Enums\Provider;
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
        'results' => array_map(fn (string $value) => ['name' => 'www.demo-domain.nl', 'type' => 'A', 'value' => $value, 'ttl' => 900], $values),
        'total' => $total,
    ]];
}

/**
 * The `records` part of every zone change sent, in order.
 *
 * @return list<mixed>
 */
function sentRecordChanges(): array
{
    return Http::recorded()
        ->filter(fn (array $pair) => $pair[0]->method() === 'PUT')
        ->map(fn (array $pair) => $pair[0]->data()['records'])
        ->values()
        ->all();
}

it('picks a zone without sending a request', function () {
    fakeOpenprovider();

    Openprovider::zone('demo-domain.nl')->provider(Provider::SECTIGO)->records();

    Http::assertNothingSent();
});

it('gets a zone with its records, keeping the SOA record apart', function () {
    $fixture = openproviderFixture('zone');
    $fixture['data']['records'][] = ['name' => 'demo-domain.nl', 'type' => 'SOA', 'value' => 'ns1.demo-domain.nl dns.openprovider.eu 2026100803 10800 3600 604800 3600', 'ttl' => 86400];
    fakeOpenprovider(['dns/zones/*' => Http::response($fixture)]);

    $zone = Openprovider::zone('demo-domain.nl')->get();

    expect($zone)
        ->id->toBe(9146574)
        ->name->toBe('demo-domain.nl')
        ->type->toBe(ZoneType::MASTER)
        ->isActive->toBeTrue()
        ->provider->toBeNull()
        ->records->toHaveCount(2)
        ->soa->toBeInstanceOf(SoaRecord::class)
        ->and($zone->soa->value)->toStartWith('ns1.demo-domain.nl')
        ->and($zone->createdAt->format('Y-m-d H:i:s'))->toBe('2019-06-27 06:22:36')
        ->and($zone->records[1])
        ->toBeInstanceOf(ZoneRecord::class)
        ->type->toBe(RecordType::MX)
        ->name->toBe('demo-domain.nl')
        ->value->toBe('mail.demo-domain.nl')
        ->ttl->toBe(86400)
        ->priority->toBe(10)
        ->zone->toBe('demo-domain.nl')
        ->raw->toHaveKey('ip', '127.0.0.1');

    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'?with_records=true');
    Http::assertSentCount(2);
});

it('gets a premium zone from its provider', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zone('demo-domain.nl')->provider(Provider::SECTIGO)->get();

    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'?with_records=true&provider=sectigo');
});

it('lists every zone lazily, a page at a time', function () {
    fakeOpenprovider(['dns/zones*' => Http::response(openproviderFixture('zones'))]);

    $zones = Openprovider::zones()->get(namePattern: 'demo*', provider: Provider::SECTIGO);

    expect($zones)->toBeInstanceOf(LazyCollection::class);
    Http::assertNothingSent();

    expect($zones->all())->toHaveCount(1)
        ->and($zones->first()->name)->toBe('demo-domain.nl');

    Http::assertSent(fn (Request $request) => $request->url() === OPENPROVIDER.'/dns/zones?limit=500&offset=0&name_pattern=demo%2A&provider=sectigo');
});

it('walks every record of a zone, a page at a time', function () {
    fakeOpenprovider(['dns/zones/demo-domain.nl/records*' => Http::sequence()
        ->push(recordsPage(3, '1.1.1.1', '2.2.2.2'))
        ->push(recordsPage(3, '3.3.3.3'))]);

    $values = Openprovider::zone('demo-domain.nl')->records()->ofType(RecordType::A)
        ->map(fn (ZoneRecord $record) => $record->value)
        ->all();

    expect($values)->toBe(['1.1.1.1', '2.2.2.2', '3.3.3.3']);

    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'/records?limit=500&offset=0&type=A');
    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'/records?limit=500&offset=2&type=A');
    Http::assertSentCount(3);
});

it('leaves the SOA record out of the records, without skipping any record after it', function () {
    $page = recordsPage(3, '1.1.1.1', '2.2.2.2');
    array_unshift($page['data']['results'], ['name' => 'demo-domain.nl', 'type' => 'SOA', 'value' => 'ns1 dns 1 2 3 4 5', 'ttl' => 86400]);
    $page['data']['results'] = array_slice($page['data']['results'], 0, 2);
    fakeOpenprovider(['dns/zones/demo-domain.nl/records*' => Http::sequence()
        ->push($page)
        ->push(recordsPage(3, '2.2.2.2'))]);

    $values = Openprovider::zone('demo-domain.nl')->records()->get()
        ->map(fn (ZoneRecord $record) => $record->value)
        ->all();

    expect($values)->toBe(['1.1.1.1', '2.2.2.2']);
    Http::assertSent(fn (Request $request) => $request->url() === ZONE.'/records?limit=500&offset=2');
});

it('stops walking at an empty page', function () {
    fakeOpenprovider(['dns/zones/demo-domain.nl/records*' => Http::response(recordsPage(10))]);

    expect(Openprovider::zone('demo-domain.nl')->records()->get()->all())->toBe([]);

    Http::assertSentCount(2);
});

it('creates a zone with records', function () {
    fakeOpenprovider(['dns/zones' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->create('demo-domain.nl', [Record::create(RecordType::A, '1.2.3.4', 'www')]);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === OPENPROVIDER.'/dns/zones'
        && $request->data() === [
            'domain' => ['name' => 'demo-domain', 'extension' => 'nl'],
            'type' => 'master',
            'records' => [['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900]],
        ]);
});

it('creates a zone with DNSSEC, a template and a provider', function () {
    fakeOpenprovider(['dns/zones' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->create('demo-domain.co.uk', dnssec: true, template: 'default', provider: Provider::SECTIGO);

    Http::assertSent(fn (Request $request) => $request->data() === [
        'domain' => ['name' => 'demo-domain', 'extension' => 'co.uk'],
        'type' => 'master',
        'secured' => true,
        'template_name' => 'default',
        'provider' => 'sectigo',
    ]);
});

it('creates a slave zone', function () {
    fakeOpenprovider(['dns/zones' => Http::response(openproviderFixture('success'))]);

    Openprovider::zones()->createSlave('demo-domain.nl', masterIp: '192.0.2.1');

    Http::assertSent(fn (Request $request) => $request->data() === [
        'domain' => ['name' => 'demo-domain', 'extension' => 'nl'],
        'type' => 'slave',
        'master_ip' => '192.0.2.1',
    ]);
});

it('deletes a zone', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);

    Openprovider::zone('demo-domain.nl')->provider(Provider::SECTIGO)->delete();

    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && $request->url() === ZONE.'?provider=sectigo');
});

it('adds records in one request', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);

    Openprovider::zone('demo-domain.nl')->records()->add(
        Record::create(RecordType::A, '1.2.3.4'),
        Record::create(RecordType::MX, 'mail.demo-domain.nl', ttl: Ttl::HOUR),
    );

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === ZONE
        && $request->data() === ['records' => ['add' => [
            ['type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
            ['type' => 'MX', 'value' => 'mail.demo-domain.nl', 'ttl' => 3600, 'prio' => Record::DEFAULT_MX_PRIORITY],
        ]]]);
    Http::assertSentCount(2);
});

it('sends spread records as a list, even with string keys', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);

    // `record` binds the first parameter; the other keys land in the variadic with their string keys.
    Openprovider::zone('demo-domain.nl')->records()->add(...['record' => Record::create(RecordType::A, '1.2.3.4'), 'second' => Record::create(RecordType::A, '5.6.7.8')]);

    expect(sentRecordChanges()[0]['add'])->toBeList()->toHaveCount(2);
});

it('updates a record you built', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);

    Openprovider::zone('demo-domain.nl')->provider(Provider::SECTIGO)->records()->update(
        Record::create(RecordType::A, '1.2.3.4', 'www'),
        Record::create(RecordType::A, '5.6.7.8', 'www'),
    );

    Http::assertSent(fn (Request $request) => $request->data() === [
        'records' => ['update' => [[
            'original_record' => ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
            'record' => ['name' => 'www', 'type' => 'A', 'value' => '5.6.7.8', 'ttl' => 900],
        ]]],
        'provider' => 'sectigo',
    ]);
});

it('lets a record read from the zone update and delete itself, one request each', function () {
    fakeOpenprovider(['dns/zones/*' => Http::sequence()
        ->push(openproviderFixture('zone'))
        ->push(openproviderFixture('success'))
        ->push(openproviderFixture('success'))]);

    [$www, $mx] = Openprovider::zone('demo-domain.nl')->get()->records;

    $www->update(Record::create(RecordType::A, '5.6.7.8', 'www'));
    $mx->delete();

    expect(sentRecordChanges())->toBe([
        // Relative names: Openprovider ignores a full name in an update or delete.
        ['update' => [[
            'original_record' => ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
            'record' => ['name' => 'www', 'type' => 'A', 'value' => '5.6.7.8', 'ttl' => 900],
        ]]],
        ['remove' => [['type' => 'MX', 'value' => 'mail.demo-domain.nl', 'ttl' => 86400, 'prio' => 10]]],
    ]);
    Http::assertSentCount(4);
});

it('deletes several records in one request', function () {
    fakeOpenprovider(['dns/zones/*' => Http::sequence()
        ->push(openproviderFixture('zone'))
        ->push(openproviderFixture('success'))]);

    $zone = Openprovider::zone('demo-domain.nl');
    $zone->records()->delete(...$zone->get()->records);

    expect(sentRecordChanges())->toBe([['remove' => [
        ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
        ['type' => 'MX', 'value' => 'mail.demo-domain.nl', 'ttl' => 86400, 'prio' => 10],
    ]]]);
});

it('keeps a record read from a premium zone at its provider', function () {
    $fixture = openproviderFixture('zone');
    $fixture['data']['provider'] = 'sectigo';
    fakeOpenprovider(['dns/zones/*' => Http::sequence()->push($fixture)->push(openproviderFixture('success'))]);

    Openprovider::zone('demo-domain.nl')->provider(Provider::SECTIGO)->get()->records[0]->delete();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request->data()['provider'] === 'sectigo');
});

it('quotes a TXT value you built to match the record Openprovider stored', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);
    $records = Openprovider::zone('demo-domain.nl')->records();
    $txt = Record::create(RecordType::TXT, 'v=spf1 -all', 'mail');

    $records->add($txt);
    $records->delete($txt);
    $records->update($txt, Record::create(RecordType::TXT, 'v=spf1 ~all', 'mail'));

    expect(sentRecordChanges())->toBe([
        ['add' => [['name' => 'mail', 'type' => 'TXT', 'value' => 'v=spf1 -all', 'ttl' => 900]]],
        ['remove' => [['name' => 'mail', 'type' => 'TXT', 'value' => '"v=spf1 -all"', 'ttl' => 900]]],
        ['update' => [[
            'original_record' => ['name' => 'mail', 'type' => 'TXT', 'value' => '"v=spf1 -all"', 'ttl' => 900],
            'record' => ['name' => 'mail', 'type' => 'TXT', 'value' => 'v=spf1 ~all', 'ttl' => 900],
        ]]],
    ]);
});

it('throws when Openprovider does not confirm a change', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(['code' => 0, 'data' => ['success' => false]])]);

    expect(fn () => Openprovider::zone('demo-domain.nl')->records()->add(Record::create(RecordType::A, '1.2.3.4')))
        ->toThrow(RequestFailed::class, 'Openprovider could not add records to `demo-domain.nl`: its answer has no `success: true`.');
});

it('url-encodes the zone name', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    Openprovider::zone('a/b.nl')->get();

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), OPENPROVIDER.'/dns/zones/a%2Fb.nl'));
});

it('leaves the client out when a record is serialized, so a queued job never stores the password', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);
    config()->set('openprovider.password', 'secret-password');
    app()->forgetInstance(SpitsOnline\Openprovider\Openprovider::class);

    $serialized = serialize(Openprovider::zone('demo-domain.nl')->get()->records[0]);

    expect($serialized)->not->toContain('secret-password')
        ->and(unserialize($serialized)->name)->toBe('www.demo-domain.nl');
});

it('sends a full name you built relative to the zone, the only form Openprovider matches', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('success'))]);
    $records = Openprovider::zone('demo-domain.nl')->records();

    $records->add(Record::create(RecordType::A, '1.2.3.4', name: 'www.demo-domain.nl'));
    $records->delete(Record::create(RecordType::A, '1.2.3.4', name: 'demo-domain.nl'));

    expect(sentRecordChanges())->toBe([
        ['add' => [['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900]]],
        ['remove' => [['type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900]]],
    ]);
});

it('compares a record read from the zone with the one you built', function () {
    fakeOpenprovider(['dns/zones/*' => Http::response(openproviderFixture('zone'))]);

    [$www, $mx] = Openprovider::zone('demo-domain.nl')->get()->records;

    expect($www->is(Record::create(RecordType::A, '1.2.3.4', name: 'www')))->toBeTrue()
        ->and($mx->is(Record::create(RecordType::MX, 'mail.demo-domain.nl', ttl: Ttl::DAY)))->toBeTrue()
        ->and($www->toRecord()->name)->toBe('www');
});
