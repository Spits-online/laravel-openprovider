<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Data\ZoneRecord;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Exceptions\OpenproviderException;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Facades\Openprovider;
use SpitsOnline\Openprovider\Testing\OpenproviderFake;

/**
 * @return list<string>
 */
function namesIn(string $zone): array
{
    return Openprovider::zone($zone)->records()->get()->map(fn (ZoneRecord $record) => "{$record->name} {$record->type->value} {$record->value}")->all();
}

it('serves seeded zones and applies record changes to them', function () {
    $www = Record::create(RecordType::A, '1.2.3.4', 'www');
    $mail = Record::create(RecordType::MX, 'mail.example.com');
    $fake = Openprovider::fake()->withZone('example.com', $www);
    $records = Openprovider::zone('example.com')->records();

    $records->add($mail);
    $records->update($www, $new = Record::create(RecordType::A, '5.6.7.8', 'www'));
    $records->delete($mail);

    expect(namesIn('example.com'))->toBe(['www.example.com A 5.6.7.8'])
        ->and($records->ofType(RecordType::MX)->all())->toBe([]);

    $fake->assertRecordAdded('example.com', fn (Record $record) => $record->is($mail));
    $fake->assertRecordUpdated('example.com', fn (Record $original, Record $record) => $original->is($www) && $record->is($new));
    $fake->assertRecordDeleted('example.com', fn (Record $record) => $record->type === RecordType::MX);

    Http::assertNothingSent();
});

it('lets records read from a fake zone update and delete themselves', function () {
    $fake = Openprovider::fake()->withZone('example.com', Record::create(RecordType::A, '1.2.3.4', 'www'), Record::create(RecordType::TXT, 'hello'));

    [$www, $txt] = Openprovider::zone('example.com')->get()->records;
    $www->update(Record::create(RecordType::A, '5.6.7.8', 'www'));
    $txt->delete();

    expect(namesIn('example.com'))->toBe(['www.example.com A 5.6.7.8']);
    $fake->assertRecordDeleted('example.com', fn (Record $record) => $record->value === '"hello"');
});

it('deletes several records in one call', function () {
    Openprovider::fake()->withZone('example.com', Record::create(RecordType::A, '1.1.1.1'), Record::create(RecordType::A, '2.2.2.2'), Record::create(RecordType::A, '3.3.3.3'));

    Openprovider::zone('example.com')->records()->delete(Record::create(RecordType::A, '1.1.1.1'), Record::create(RecordType::A, '3.3.3.3'));

    expect(namesIn('example.com'))->toBe(['example.com A 2.2.2.2']);
});

it('stores TXT values quoted like Openprovider, and still deletes the record you built', function () {
    $fake = Openprovider::fake()->withZone('demo-domain.nl');
    $txt = Record::create(RecordType::TXT, 'v=spf1 -all');

    Openprovider::zone('demo-domain.nl')->records()->add($txt);

    expect(namesIn('demo-domain.nl'))->toBe(['demo-domain.nl TXT "v=spf1 -all"']);

    Openprovider::zone('demo-domain.nl')->records()->delete($txt);

    expect(namesIn('demo-domain.nl'))->toBe([]);
    $fake->assertRecordDeleted('demo-domain.nl');
});

it('stores records under their full name like Openprovider, and still matches the short name', function () {
    Openprovider::fake()->withZone(
        'example.com',
        Record::create(RecordType::A, '1.2.3.4'),
        Record::create(RecordType::A, '1.2.3.4', 'www'),
        Record::create(RecordType::A, '1.2.3.4', 'shop.example.com'),
    );

    expect(namesIn('example.com'))->toBe(['example.com A 1.2.3.4', 'www.example.com A 1.2.3.4', 'shop.example.com A 1.2.3.4']);

    Openprovider::zone('example.com')->records()->delete(Record::create(RecordType::A, '1.2.3.4', 'www'), Record::create(RecordType::A, '1.2.3.4', 'shop.example.com'));

    expect(namesIn('example.com'))->toBe(['example.com A 1.2.3.4']);
});

it('creates, lists and deletes zones', function () {
    $fake = Openprovider::fake();

    Openprovider::zones()->create('example.com', [Record::create(RecordType::A, '1.2.3.4')]);
    Openprovider::zones()->createSlave('example.nl', masterIp: '192.0.2.1');

    expect(Openprovider::zones()->get(namePattern: '*.com')->count())->toBe(1)
        ->and(Openprovider::zones()->get()->count())->toBe(2)
        ->and(namesIn('example.com'))->toBe(['example.com A 1.2.3.4']);

    Openprovider::zone('example.com')->delete();

    $fake->assertZoneCreated('example.nl');
    $fake->assertZoneDeleted('example.com');
    expect(fn () => Openprovider::zone('example.com')->get())->toThrow(RequestFailed::class);
});

it('answers a missing zone like Openprovider would, with a 404', function () {
    Openprovider::fake();

    expect(fn () => Openprovider::zone('missing.com')->records()->add(Record::create(RecordType::A, '1.2.3.4')))
        ->toThrow(fn (RequestFailed $e) => expect($e->status)->toBe(404));
});

it('serves seeded domains and records domain changes', function () {
    $fake = Openprovider::fake()->withDomain('example.com');

    $domain = Openprovider::domains()->find('example.com');

    $domain->update(autorenew: Autorenew::ON);
    $domain->lock();
    $domain->renew();
    $domain->restore();
    $registered = Openprovider::domains()->register('example.nl', owner: 'CV904717-NL');
    Openprovider::domains()->transfer('example.org', authCode: 'code', owner: 'CV904717-NL');
    $domain->delete();

    expect($registered->id)->toBe(2)
        ->and(Openprovider::domain($registered->id)->get()->name->extension)->toBe('nl')
        ->and($registered->authCode())->toBe(OpenproviderFake::AUTH_CODE)
        ->and(Openprovider::domains()->get()->count())->toBe(2)
        ->and(Openprovider::domains()->check(['example.nl', 'free.nl']))
        ->sequence(
            fn ($check) => $check->isAvailable()->toBeFalse(),
            fn ($check) => $check->isAvailable()->toBeTrue(),
        );

    $fake->assertDomainUpdated($domain->id, fn (array $changes) => $changes === ['autorenew' => 'on']);
    $fake->assertDomainUpdated($domain->id, fn (array $changes) => $changes === ['is_locked' => true]);
    $fake->assertDomainRenewed($domain->id);
    $fake->assertDomainRestored($domain->id);
    $fake->assertDomainRegistered('example.nl');
    $fake->assertDomainTransferred('example.org');
    $fake->assertDomainDeleted($domain->id);
});

it('answers a missing domain like Openprovider would, with a 404', function () {
    Openprovider::fake();

    expect(Openprovider::domains()->find('missing.com'))->toBeNull()
        ->and(fn () => Openprovider::domain(99)->renew())->toThrow(fn (RequestFailed $e) => expect($e->status)->toBe(404));
});

it('fails assertions for changes that did not happen', function (Closure $assertion) {
    Openprovider::fake()->withZone('example.com');

    expect($assertion)->toThrow(AssertionFailedError::class);
})->with([
    'record added' => fn () => Openprovider::assertRecordAdded('example.com'),
    'zone created' => fn () => Openprovider::assertZoneCreated('example.com'),
    'domain renewed' => fn () => Openprovider::assertDomainRenewed(1),
]);

it('fails assertNothingChanged after a change', function () {
    $fake = Openprovider::fake();
    $fake->assertNothingChanged();

    Openprovider::zones()->create('example.com');

    expect(fn () => $fake->assertNothingChanged())->toThrow(AssertionFailedError::class);
});

it('hands out the same fake auth code after a reset', function () {
    Openprovider::fake()->withDomain('example.com');

    expect(Openprovider::domain(1)->resetAuthCode())->toBe(OpenproviderFake::AUTH_CODE);
});

it('refuses a request the fake does not support', function () {
    expect(fn () => Openprovider::fake()->request('get', 'customers', 'list the customers'))
        ->toThrow(OpenproviderException::class, "The Openprovider fake can't list the customers.");
});

it('ignores a full name in a delete or update, like Openprovider does', function () {
    $fake = Openprovider::fake()->withZone('example.com', Record::create(RecordType::A, '1.2.3.4', name: 'www'));

    $fake->request('put', 'dns/zones/example.com', 'delete records', body: ['records' => ['remove' => [
        ['name' => 'www.example.com', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
    ]]]);

    expect(namesIn('example.com'))->toBe(['www.example.com A 1.2.3.4']);
});

it('shares one TTL between records with the same name and type, like Openprovider', function () {
    Openprovider::fake()->withZone('example.com', Record::create(RecordType::TXT, 'v=spf1 -all', ttl: Ttl::DAY));
    $records = Openprovider::zone('example.com')->records();
    $ttls = fn () => $records->ofType(RecordType::TXT)->map(fn (ZoneRecord $record) => $record->ttl)->all();

    // A new record takes the TTL the others already have.
    $records->add(Record::create(RecordType::TXT, 'verification=123'));
    expect($ttls())->toBe([86400, 86400]);

    // Updating one record's TTL changes it for all of them.
    $records->update(Record::create(RecordType::TXT, 'verification=123', ttl: Ttl::DAY), Record::create(RecordType::TXT, 'verification=123', ttl: Ttl::HOUR));
    expect($ttls())->toBe([3600, 3600]);
});
