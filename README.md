<div align="left">
  <a href="https://github.com/Spits-online/laravel-openprovider">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Spits-online/laravel-openprovider/main/art/banner-dark.png">
      <img alt="Laravel Openprovider by Spits" src="https://raw.githubusercontent.com/Spits-online/laravel-openprovider/main/art/banner-light.png">
    </picture>
  </a>

<h1>Openprovider domains and DNS for Laravel</h1>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spits-online/laravel-openprovider.svg?style=flat-square)](https://packagist.org/packages/spits-online/laravel-openprovider)
[![Tests](https://img.shields.io/github/actions/workflow/status/Spits-online/laravel-openprovider/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Spits-online/laravel-openprovider/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/Spits-online/laravel-openprovider/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/Spits-online/laravel-openprovider/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/spits-online/laravel-openprovider.svg?style=flat-square)](https://packagist.org/packages/spits-online/laravel-openprovider)

</div>

A typed client for the domains and DNS zones in your [Openprovider](https://www.openprovider.com) account. It logs in for you, returns data objects instead of arrays, and ships a fake for your tests. It can also add JSON routes for managing DNS records and exporting a zone to Excel.

```php
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Facades\Openprovider;

Openprovider::zones()->addRecords('example.com', [
    Record::create(type: RecordType::A, value: '1.2.3.4', name: 'www'),
]);

$zone = Openprovider::zones()->find('example.com');
$zone->records; // list<Record>
```

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- An Openprovider account with API access

## Installation

```bash
composer require spits-online/laravel-openprovider
```

Add your Openprovider login to `.env`:

```env
OPENPROVIDER_USERNAME=
OPENPROVIDER_PASSWORD=
# The IP address of the server that calls the API, sent with the login
OPENPROVIDER_IP=
```

The account needs API access, which you enable in the Openprovider control panel.

That's all the configuration most apps need, so you don't have to publish a config file. When you do want to change something, such as [enabling the DNS record routes](#dns-record-routes), create `config/openprovider.php` with **only the keys you change**. It's merged over the [package defaults](config/openprovider.php) key by key, so everything you leave out keeps its default:

```php
// config/openprovider.php
return [
    'routes' => [
        'enabled' => true,
    ],
];
```

Don't copy keys at their default value. A copied default looks like a deliberate choice, and it stops following the package when the default changes. To see every option, you can publish the full file with `php artisan vendor:publish --tag="openprovider-config"`. Keep the keys you change and delete the rest.

### Testing against the sandbox

Openprovider has a sandbox for trying things without buying anything. Point the package at it with:

```env
OPENPROVIDER_BASE_URL=http://api.sandbox.openprovider.nl:8480/v1beta
```

### How the login works

The package logs in on the first request and caches the token for 47 hours. Openprovider's tokens are valid for 48, so a cached token never expires mid-request. Each account has its own cached token, and a failed login caches nothing.

## Managing DNS records

Records are `Record` objects. Build one with `Record::create()`:

```php
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;

$mx = Record::create(
    type: RecordType::MX,
    value: 'mail.example.com',
    ttl: Ttl::HOUR,
    prio: 10,
);
```

- `name` is the host before the zone name, such as `www`. Leave it out for the zone itself.
- `ttl` is one of the TTLs Openprovider accepts: `Ttl::FIFTEEN_MINUTES` (the default), `Hour`, `ThreeHours`, `SixHours`, `TwelveHours` or `Day`. Openprovider would silently save any other value as a day.
- `prio` is the priority, which MX records need.

Add, change and remove records:

```php
use SpitsOnline\Openprovider\Facades\Openprovider;

Openprovider::zones()->addRecords('example.com', [$mx]);

Openprovider::zones()->updateRecord('example.com', $original, $changed);

Openprovider::zones()->removeRecords('example.com', [$mx]);
```

`updateRecord()` and `removeRecords()` find the record by its name, type, value, TTL and priority. Pass records the way Openprovider returned them, for example from `find()`:

```php
$zone = Openprovider::zones()->find('example.com');

$old = collect($zone->records)
    ->firstWhere('type', RecordType::MX);

Openprovider::zones()->removeRecords('example.com', [$old]);
```

SOA records are generated by Openprovider and can't be changed. `RecordType::SOA->isEditable()` returns `false`.

### Reading records

`find()` returns the zone with its records:

```php
$zone = Openprovider::zones()->find('example.com');

$zone->name;     // "example.com"
$zone->type;     // ZoneType::MASTER
$zone->records;  // list<Record>
$zone->raw;      // the full answer from Openprovider
```

For large zones, `records()` returns a lazy collection that fetches the records 500 at a time, as you use them:

```php
Openprovider::zones()
    ->records('example.com', type: RecordType::TXT)
    ->each(fn (Record $record) => /* ... */);
```

### Premium DNS

Every zone method takes a `provider` argument. Pass `'sectigo'` to work with a premium DNS zone:

```php
Openprovider::zones()->find('example.com', provider: 'sectigo');
```

## Managing zones

```php
use SpitsOnline\Openprovider\Facades\Openprovider;

// One page, or every zone lazily
$page = Openprovider::zones()->list(limit: 100, namePattern: 'example*');
$page->items;   // list<Zone>
$page->total;   // across all pages

Openprovider::zones()->all()->each(/* ... */);

Openprovider::zones()->create('example.com', records: [$mx]);

// A slave zone copies its records from a master server
Openprovider::zones()->create('example.com', masterIp: '192.0.2.1');

Openprovider::zones()->delete('example.com');
```

`create()` also takes `isDnssecEnabled`, a `template` name and a `provider`. Openprovider can't restore a deleted zone.

## Managing domains

```php
use SpitsOnline\Openprovider\Facades\Openprovider;

$domain = Openprovider::domains()->find(1222095);
$domain = Openprovider::domains()->findByName('example.com'); // or null

$domain->id;           // 1222095
(string) $domain->name; // "example.com"
$domain->status;       // "ACT" (active) or "REQ" (requested)
$domain->autorenew;    // Autorenew::OFF
$domain->nameServers;  // list<Nameserver>
$domain->renewalDate;  // "2026-09-27 07:03:04", as Openprovider sends it
$domain->raw;          // the full answer from Openprovider
```

List domains a page at a time, or all of them lazily. The pattern matches the name without its extension:

```php
$page = Openprovider::domains()->list(pattern: 'example*', status: 'ACT');

Openprovider::domains()->all()->each(/* ... */);
```

### Registering a domain

Check whether it's available first:

```php
$check = Openprovider::domains()->check(['example.com'])[0];

$check->isAvailable(); // true when the status is "free"
```

Then register it. Contact handles are Openprovider customer handles. Openprovider charges your account for the registration.

```php
use SpitsOnline\Openprovider\Enums\Autorenew;

$domain = Openprovider::domains()->create(
    'example.com',
    ownerHandle: 'CV904717-NL',
    adminHandle: 'CV904717-NL',
    techHandle: 'CV904717-NL',
    nameServers: ['ns1.op.eu', 'ns2.op.nl'],
    autorenew: Autorenew::ON,
);
```

To transfer a domain in, use `transfer()` with the same arguments plus its `authCode`.

`create()`, `transfer()` and `update()` take an `attributes` array for any other field Openprovider documents for that request, such as `['promo_code' => 'SPRING']`.

### Changing a domain

`update()` only changes the arguments you pass:

```php
Openprovider::domains()->update(
    $domain->id,
    nsGroup: 'my-nameservers',
    isLocked: true,
);
```

The other domain methods:

```php
Openprovider::domains()->renew($domain->id, period: 2);
Openprovider::domains()->restore($domain->id);
Openprovider::domains()->delete($domain->id);

$code = Openprovider::domains()->authCode($domain->id);
$code = Openprovider::domains()->resetAuthCode($domain->id);
```

## DNS record routes

The package can register JSON routes for apps that manage DNS records from their own front end. They are off until you enable them:

```php
// config/openprovider.php
return [
    'routes' => [
        'enabled' => true,
    ],
];
```

| Method | URI | Name | Does |
|---|---|---|---|
| `GET` | `dns-zone/records/{domain}` | `dns-zone.records.show` | Returns the zone with its records as `data` |
| `POST` | `dns-zone/records/{domain}` | `dns-zone.records.store` | Adds the `record` |
| `PUT` | `dns-zone/records/{domain}` | `dns-zone.records.update` | Changes `original_record` into `record` |
| `DELETE` | `dns-zone/records/{domain}` | `dns-zone.records.destroy` | Removes the `records` |

The routes change live DNS, so they run behind `web` and `auth` by default. Any signed-in user can change any zone in the account, so add your own middleware when users may only manage some domains. Set the middleware and a URI prefix in the config:

```php
// config/openprovider.php
return [
    'routes' => [
        'enabled' => true,
        'prefix' => 'admin',
        'middleware' => ['api', 'auth:sanctum'],
    ],
];
```

A record in a request body has the same fields as `Record::create()`:

```json
{
    "record": {
        "name": "www",
        "type": "A",
        "value": "1.2.3.4",
        "ttl": 900
    }
}
```

Invalid requests get Laravel's standard `422` validation response. Changes answer `204 No Content`. Each request may send a `provider` to change a premium DNS zone.

### Exporting a zone to Excel

A route that downloads a zone's records as `.xlsx` has its own switch, separate from the DNS record routes. It needs [Laravel Excel](https://laravel-excel.com):

```bash
composer require maatwebsite/excel
```

```php
// config/openprovider.php
return [
    'exports' => [
        'enabled' => true,
    ],
];
```

Like the DNS record routes, it runs behind `web` and `auth` by default, and takes its own `exports.prefix` and `exports.middleware`.

| Method | URI | Name | Does |
|---|---|---|---|
| `GET` | `dns-zone/export/records/{domain}` | `dns-zone.export` | Downloads the records as `.xlsx` |

The download is named `dns_zone_{domain}.xlsx`. Pass `?filename=example` to name it `example.xlsx` instead. The name may contain letters, digits, spaces, dots, dashes and underscores.

You can also use the export in your own code:

```php
use Maatwebsite\Excel\Facades\Excel;
use SpitsOnline\Openprovider\Exports\ZoneExport;

$records = Openprovider::zones()->find('example.com')->records;

return Excel::download(new ZoneExport($records), 'example.com.xlsx');
```

## Error handling

Every exception extends `SpitsOnline\Openprovider\Exceptions\OpenproviderException`:

| Exception | When |
|---|---|
| `RequestFailed` | Openprovider returned an error. `$status`, `$errorCode` and `$body` hold what it sent back. |
| `ConnectionFailed` | Openprovider couldn't be reached. |
| `MissingConfiguration` | The username or password isn't set. The message names the env key. |
| `InvalidDomainName` | A domain name has no extension, such as `localhost`. |
| `MissingDependency` | The export route is used without `maatwebsite/excel`. |

```php
use SpitsOnline\Openprovider\Exceptions\RequestFailed;

try {
    $domain = Openprovider::domains()->find($id);
} catch (RequestFailed $e) {
    report($e);

    return back()->with('error', $e->body['desc'] ?? 'Openprovider failed');
}
```

## Testing your app

`Openprovider::fake()` swaps the client for an in-memory Openprovider. Seed it with zones and domains; changes apply to the seeded data, and you can assert on them:

```php
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Facades\Openprovider;

$fake = Openprovider::fake()
    ->withZone('example.com', [
        Record::create(type: RecordType::A, value: '1.2.3.4'),
    ])
    ->withDomain('example.com');

// ... run the code under test ...

Openprovider::assertRecordAdded(
    'example.com',
    fn (Record $record) => $record->value === '5.6.7.8',
);
Openprovider::assertRecordUpdated('example.com');
Openprovider::assertRecordRemoved('example.com');
Openprovider::assertZoneCreated('example.nl');
Openprovider::assertZoneDeleted('example.nl');
Openprovider::assertDomainRegistered('example.nl');
Openprovider::assertDomainTransferred('example.org');
Openprovider::assertDomainUpdated(1, fn (array $changes) => /* ... */);
Openprovider::assertDomainRenewed(1);
Openprovider::assertDomainRestored(1);
Openprovider::assertDomainDeleted(1);
Openprovider::assertNothingChanged();
```

A zone or domain that wasn't seeded throws `RequestFailed` with status `404`. `check()` reports seeded domains as `in use` and every other name as `free`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently. Upgrading from 1.x? See [UPGRADE](UPGRADE.md).

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [SpitsOnline](https://spits.online)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
