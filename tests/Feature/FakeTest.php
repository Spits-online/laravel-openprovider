<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\Autorenew;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Exceptions\OpenproviderException;
use SpitsOnline\Openprovider\Exceptions\RequestFailed;
use SpitsOnline\Openprovider\Facades\Openprovider;
use SpitsOnline\Openprovider\Testing\FakeDomains;

it('serves seeded zones and applies record changes to them', function () {
    $www = Record::create(type: RecordType::A, value: '1.2.3.4', name: 'www');
    $mail = Record::create(type: RecordType::MX, value: 'mail.example.com', prio: 10);
    $fake = Openprovider::fake()->withZone('example.com', [$www]);

    Openprovider::zones()->addRecords('example.com', [$mail]);
    Openprovider::zones()->updateRecord('example.com', $www, $new = Record::create(type: RecordType::A, value: '5.6.7.8', name: 'www'));
    Openprovider::zones()->removeRecords('example.com', [$mail]);

    // Stored under the full name, the way Openprovider stores it.
    expect(Openprovider::zones()->find('example.com')->records)->toEqual([Record::create(type: RecordType::A, value: '5.6.7.8', name: 'www.example.com')])
        ->and(Openprovider::zones()->records('example.com', RecordType::MX)->all())->toBe([]);

    $fake->assertRecordAdded('example.com', fn (Record $record) => $record->is($mail));
    $fake->assertRecordUpdated('example.com', fn (Record $original, Record $record) => $original->is($www) && $record->is($new));
    $fake->assertRecordRemoved('example.com', fn (Record $record) => $record->type === RecordType::MX);

    Http::assertNothingSent();
});

it('stores TXT values quoted like Openprovider, and still removes the record you created', function () {
    $fake = Openprovider::fake()->withZone('demo-domain.nl');
    $txt = Record::create(RecordType::TXT, 'v=spf1 -all');

    Openprovider::zones()->addRecords('demo-domain.nl', [$txt]);

    expect(Openprovider::zones()->find('demo-domain.nl')->records[0]->value)->toBe('"v=spf1 -all"');

    Openprovider::zones()->removeRecords('demo-domain.nl', [$txt]);

    expect(Openprovider::zones()->find('demo-domain.nl')->records)->toBe([]);
    $fake->assertRecordRemoved('demo-domain.nl');
});

it('stores records under their full name like Openprovider, and still matches the short name', function () {
    Openprovider::fake()->withZone('example.com', [
        Record::create(RecordType::A, '1.2.3.4'),
        Record::create(RecordType::A, '1.2.3.4', 'www'),
        Record::create(RecordType::A, '1.2.3.4', 'shop.example.com'),
    ]);

    expect(array_map(fn (Record $record) => $record->name, Openprovider::zones()->find('example.com')->records))
        ->toBe(['example.com', 'www.example.com', 'shop.example.com']);

    Openprovider::zones()->removeRecords('example.com', [Record::create(RecordType::A, '1.2.3.4', 'www')]);
    Openprovider::zones()->removeRecords('example.com', [Record::create(RecordType::A, '1.2.3.4', 'shop.example.com')]);

    expect(array_map(fn (Record $record) => $record->name, Openprovider::zones()->find('example.com')->records))
        ->toBe(['example.com']);
});

it('creates, lists and deletes zones', function () {
    $fake = Openprovider::fake();

    Openprovider::zones()->create('example.com');
    Openprovider::zones()->create('example.nl');

    expect(Openprovider::zones()->list(namePattern: '*.com')->items)->toHaveCount(1)
        ->and(Openprovider::zones()->all()->count())->toBe(2);

    Openprovider::zones()->delete('example.com');

    $fake->assertZoneCreated('example.nl');
    $fake->assertZoneDeleted('example.com');
    expect(fn () => Openprovider::zones()->find('example.com'))->toThrow(RequestFailed::class);
});

it('answers a missing zone like Openprovider would, with a 404', function () {
    Openprovider::fake();

    expect(fn () => Openprovider::zones()->addRecords('missing.com', []))
        ->toThrow(fn (RequestFailed $e) => expect($e->status)->toBe(404));
});

it('serves seeded domains and records domain changes', function () {
    $fake = Openprovider::fake()->withDomain('example.com');

    $domain = Openprovider::domains()->findByName('example.com');

    Openprovider::domains()->update($domain->id, autorenew: Autorenew::ON);
    Openprovider::domains()->renew($domain->id);
    Openprovider::domains()->restore($domain->id);
    $registered = Openprovider::domains()->create('example.nl', ownerHandle: 'CV904717-NL');
    Openprovider::domains()->transfer('example.org', authCode: 'code', ownerHandle: 'CV904717-NL');
    Openprovider::domains()->delete($domain->id);

    expect($registered->id)->toBe(2)
        ->and(Openprovider::domains()->authCode($registered->id))->toBe('fake-auth-code')
        ->and(Openprovider::domains()->all()->count())->toBe(2)
        ->and(Openprovider::domains()->check(['example.nl', 'free.nl']))
        ->sequence(
            fn ($check) => $check->isAvailable()->toBeFalse(),
            fn ($check) => $check->isAvailable()->toBeTrue(),
        );

    $fake->assertDomainUpdated($domain->id, fn (array $changes) => $changes === ['autorenew' => Autorenew::ON]);
    $fake->assertDomainRenewed($domain->id);
    $fake->assertDomainRestored($domain->id);
    $fake->assertDomainRegistered('example.nl');
    $fake->assertDomainTransferred('example.org');
    $fake->assertDomainDeleted($domain->id);
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

    expect(Openprovider::domains()->resetAuthCode(1))->toBe(FakeDomains::AUTH_CODE);
});

it('never sends a raw request', function () {
    expect(fn () => Openprovider::fake()->request('get', 'domains', 'list the domains'))
        ->toThrow(OpenproviderException::class, "The Openprovider fake can't list the domains");
});
