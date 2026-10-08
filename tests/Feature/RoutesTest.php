<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Exports\ZoneExport;
use SpitsOnline\Openprovider\Facades\Openprovider;
use SpitsOnline\Openprovider\OpenproviderServiceProvider;
use SpitsOnline\Openprovider\Testing\OpenproviderFake;

/**
 * Enable the routes the way an app does: in its config, before the package boots.
 *
 * @param  array<string, mixed>  $routes
 */
function enableRoutes(array $routes = []): void
{
    config()->set('openprovider.routes', [...config('openprovider.routes'), 'enabled' => true, ...$routes]);

    (new OpenproviderServiceProvider(app()))->packageBooted();

    app('router')->getRoutes()->refreshNameLookups();
}

/**
 * @return array<string, mixed>
 */
function record(string $value = '1.2.3.4', array $overrides = []): array
{
    return [...['name' => 'www', 'type' => 'A', 'value' => $value, 'ttl' => 900], ...$overrides];
}

function fakeZone(): OpenproviderFake
{
    return Openprovider::fake()->withZone('demo-domain.nl', [
        Record::create(type: RecordType::A, value: '1.2.3.4', name: 'www'),
    ]);
}

it('registers no routes unless the app enables them', function () {
    expect(Route::has('dns-zone.records.show'))->toBeFalse();
});

it('registers the routes behind web and auth by default', function () {
    enableRoutes();

    $route = Route::getRoutes()->getByName('dns-zone.records.show');

    expect($route->uri())->toBe('dns-zone/records/{domain}')
        ->and($route->gatherMiddleware())->toBe(['web', 'auth'])
        ->and(Route::has(['dns-zone.records.store', 'dns-zone.records.update', 'dns-zone.records.destroy', 'dns-zone.export']))->toBeTrue();
});

it('applies the configured prefix and middleware', function () {
    enableRoutes(['prefix' => 'admin', 'middleware' => ['api']]);

    $route = Route::getRoutes()->getByName('dns-zone.records.show');

    expect($route->uri())->toBe('admin/dns-zone/records/{domain}')
        ->and($route->gatherMiddleware())->toBe(['api']);
});

it('refuses guests', function () {
    enableRoutes();
    fakeZone();

    $this->getJson('dns-zone/records/demo-domain.nl')->assertUnauthorized();
});

it('shows the zone with its records', function () {
    enableRoutes();
    fakeZone();

    $this->actingAs(new User)
        ->getJson('dns-zone/records/demo-domain.nl')
        ->assertOk()
        ->assertJsonPath('data.name', 'demo-domain.nl')
        ->assertJsonPath('data.records', [record()]);
});

it('adds a record', function () {
    enableRoutes();
    $fake = fakeZone();

    $this->actingAs(new User)
        ->postJson('dns-zone/records/demo-domain.nl', ['record' => record('5.6.7.8')])
        ->assertNoContent();

    $fake->assertRecordAdded('demo-domain.nl', fn (Record $record) => $record->value === '5.6.7.8');
});

it('updates a record', function () {
    enableRoutes();
    $fake = fakeZone();

    $this->actingAs(new User)
        ->putJson('dns-zone/records/demo-domain.nl', ['original_record' => record(), 'record' => record('5.6.7.8')])
        ->assertNoContent();

    $fake->assertRecordUpdated('demo-domain.nl', fn (Record $original, Record $record) => $original->value === '1.2.3.4' && $record->value === '5.6.7.8');
});

it('removes records', function () {
    enableRoutes();
    $fake = fakeZone();

    $this->actingAs(new User)
        ->deleteJson('dns-zone/records/demo-domain.nl', ['records' => [record()]])
        ->assertNoContent();

    $fake->assertRecordRemoved('demo-domain.nl');
    expect(Openprovider::zones()->find('demo-domain.nl')->records)->toBe([]);
});

it('validates a new record', function (array $record, string $field) {
    enableRoutes();
    $fake = fakeZone();

    $this->actingAs(new User)
        ->postJson('dns-zone/records/demo-domain.nl', ['record' => $record])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    $fake->assertNothingChanged();
})->with([
    'missing type' => [record(overrides: ['type' => null]), 'record.type'],
    'unknown type' => [record(overrides: ['type' => 'PTR']), 'record.type'],
    'SOA, which Openprovider manages' => [record(overrides: ['type' => 'SOA']), 'record.type'],
    'missing value' => [record(overrides: ['value' => '']), 'record.value'],
    'TTL Openprovider does not accept' => [record(overrides: ['ttl' => 600]), 'record.ttl'],
    'MX without priority' => [record('mail.demo-domain.nl', ['type' => 'MX']), 'record.prio'],
]);

it('accepts any stored TTL on the record being changed', function () {
    enableRoutes();
    $fake = Openprovider::fake()->withZone('demo-domain.nl', [new Record(RecordType::A, '1.2.3.4', 'www', 600)]);

    $this->actingAs(new User)
        ->putJson('dns-zone/records/demo-domain.nl', [
            'original_record' => record(overrides: ['ttl' => 600]),
            'record' => record(overrides: ['ttl' => Ttl::Hour->value]),
        ])
        ->assertNoContent();

    $fake->assertRecordUpdated('demo-domain.nl');
});

it('requires at least one record to remove', function () {
    enableRoutes();
    fakeZone();

    $this->actingAs(new User)
        ->deleteJson('dns-zone/records/demo-domain.nl', ['records' => []])
        ->assertJsonValidationErrors('records');
});

it('exports the records as a spreadsheet', function () {
    enableRoutes();
    fakeZone();
    Excel::fake();

    $this->actingAs(new User)->get('dns-zone/export/records/demo-domain.nl')->assertOk();

    Excel::assertDownloaded('dns_zone_demo-domain.nl.xlsx', fn (ZoneExport $export) => $export->collection()->count() === 1);
});
