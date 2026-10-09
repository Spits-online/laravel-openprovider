<!--
  Keep this README in sync with the code. Every change to the public API, config,
  routes, exceptions or the fake updates this file in the same commit: every
  feature has a working example here, and nothing is shown that doesn't exist.
-->

<div align="left">
  <a href="https://github.com/Spits-online/laravel-openprovider">
    <picture>
      <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Spits-online/laravel-openprovider/main/art/banner-dark.png">
      <img alt="Laravel Openprovider by Spits" src="https://raw.githubusercontent.com/Spits-online/laravel-openprovider/main/art/banner-light.png">
    </picture>
  </a>

<h1>Manage Openprovider domains and DNS in Laravel</h1>

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spits-online/laravel-openprovider.svg?style=flat-square)](https://packagist.org/packages/spits-online/laravel-openprovider)
[![Tests](https://img.shields.io/github/actions/workflow/status/Spits-online/laravel-openprovider/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Spits-online/laravel-openprovider/actions/workflows/run-tests.yml)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/Spits-online/laravel-openprovider/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/Spits-online/laravel-openprovider/actions/workflows/phpstan.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/spits-online/laravel-openprovider.svg?style=flat-square)](https://packagist.org/packages/spits-online/laravel-openprovider)

</div>

Work with the domains and DNS records in your [Openprovider](https://www.openprovider.com) account from Laravel. Add a record, register a domain, or export a zone to Excel with a single call.

```php
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Facades\Openprovider;

Openprovider::zone('example.com')
    ->records()
    ->add(Record::create(RecordType::A, '1.2.3.4', name: 'www'));
Openprovider::zone('example.com')->records()->get();

Openprovider::domains()->find('example.com')->renew();
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
OPENPROVIDER_IP=
```

`OPENPROVIDER_IP` is the IP address of the server that calls the API; it's sent with the login.

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

### Dates and timezones

Dates such as `$domain->expirationDate` and `$zone->createdAt` are immutable Carbon instances. Openprovider sends them without an offset (`2026-09-27 07:03:04`) and doesn't document their timezone, so the package reads them in your app's timezone. Set another one if you know Openprovider's dates are in it:

```env
OPENPROVIDER_TIMEZONE=Europe/Amsterdam
```

### Using another account

The facade uses the account in your config. Build a client for another account with `fromConfig()`, which takes the same keys:

```php
use SpitsOnline\Openprovider\Openprovider;

$reseller = Openprovider::fromConfig([
    'username' => 'reseller',
    'password' => 'secret',
    'ip' => '203.0.113.10',
]);

$reseller->zone('example.com')->records()->get();
```

`Openprovider::fake()` only replaces the client behind the facade. A client you build with `fromConfig()` still calls Openprovider, so fake its requests in your tests with `Http::fake()`.

### How the login works

The package logs in on the first request and caches the token for 47 hours. Openprovider's tokens are valid for 48, so a cached token never expires mid-request. Each account has its own cached token, and a failed login caches nothing.

## Managing DNS records

Pick a zone with `Openprovider::zone()`, then work with its `records()`. Picking a zone sends no request; every action after it sends exactly one:

```php
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Facades\Openprovider;

Openprovider::zone('example.com')->records()->add(
    Record::create(RecordType::A, '1.2.3.4', name: 'www'),
    Record::create(RecordType::MX, 'mail.example.com'),
);
```

### Building a record

Build a record with `Record::create()`:

```php
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;

Record::create(RecordType::A, '1.2.3.4');                    // the domain itself
Record::create(RecordType::A, '1.2.3.4', name: 'www');       // www.example.com
Record::create(RecordType::MX, 'mail.example.com', ttl: Ttl::HOUR, priority: 5);
Record::create(RecordType::TXT, 'v=spf1 include:_spf.example.com -all');
```

- `name` is the host before the zone name, such as `www`. Leave it out for the domain itself.
- `ttl` is one of the TTLs Openprovider accepts: `Ttl::FIFTEEN_MINUTES` (the default), `Ttl::HOUR`, `Ttl::THREE_HOURS`, `Ttl::SIX_HOURS`, `Ttl::TWELVE_HOURS` or `Ttl::DAY`. Openprovider would silently save any other value as a day.
  Records with the same name and type share one TTL, as DNS requires. A record you add takes the TTL the others already have, and updating one record's TTL changes it for all of them. Adding a TXT verification record to a domain with a one-day SPF record and then updating it with `Ttl::FIFTEEN_MINUTES` gives the SPF record a 15-minute TTL too.
- `priority` is for MX and SRV records. An MX record gets priority `10` when you leave it out (`Record::DEFAULT_MX_PRIORITY`), because Openprovider requires one.

Every type in `RecordType` can be built except `SOA`, which Openprovider generates: `Record::create(RecordType::SOA, …)` throws `InvalidRecord`.

### Reading records

`records()->get()` returns every record of the zone as a lazy collection that fetches them 500 at a time, as you use them. `ofType()` lets Openprovider filter them:

```php
use SpitsOnline\Openprovider\Data\ZoneRecord;
use SpitsOnline\Openprovider\Enums\RecordType;

$records = Openprovider::zone('example.com')->records()->get();
$records = Openprovider::zone('example.com')->records()->ofType(RecordType::TXT);

$record = $records->first();
$record->name;      // "www.example.com": Openprovider returns the full name
$record->type;      // RecordType::A
$record->value;     // "1.2.3.4", or "\"v=spf1 -all\"" for a TXT record: Openprovider quotes TXT values
$record->ttl;       // 900
$record->priority;  // 10 for an MX record, otherwise null
$record->zone;      // "example.com"
$record->raw;       // the full answer from Openprovider
```

### Changing and deleting records

A record you read from a zone is a `ZoneRecord`. It knows its zone, so it can update or delete itself, with one request each:

```php
$record->update(Record::create(RecordType::A, '5.6.7.8', name: 'www'));
$record->delete();
```

To delete several records at once, pass them all to `delete()`. It sends one request however many you pass:

```php
$zone = Openprovider::zone('example.com');

$zone->records()->delete($first, $second);
$zone->records()->delete(...$zone->records()->ofType(RecordType::TXT));
```

Unlike Eloquent's `$user->posts()->delete()`, this never deletes every record: it needs at least one, and only deletes the ones you pass.

Openprovider returns full names (`www.example.com`), but only matches a record to update or delete by its name relative to the zone (`www`). With a full name, it reports success and does nothing: an update adds the new record and keeps the old one. The package always sends relative names, so this is handled for you, including when you build a record with a full name.

`records()->update()` and `records()->delete()` also take a record you built, matched by its name, type, value, TTL and priority. Openprovider saves TXT values wrapped in quotes and only matches a TXT record quoted the same way, so the package adds the quotes for you:

```php
$zone->records()->update(Record::create(RecordType::A, '1.2.3.4', name: 'www'), Record::create(RecordType::A, '5.6.7.8', name: 'www'));
$zone->records()->delete(Record::create(RecordType::TXT, 'v=spf1 -all'));
```

To compare records or send them on yourself:

```php
$record->is($other);   // same name, type, value, TTL and priority, ignoring TXT quotes
$record->toRecord();   // a ZoneRecord as a Record you can change and add elsewhere
$record->toArray();    // ['name' => 'www.example.com', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900]
Record::create(RecordType::TXT, 'x')->stored()->value;  // "\"x\"", the value as Openprovider stores it
```

### The SOA record

Openprovider generates every zone's SOA record, so it can't be added, changed or deleted. It isn't one of the zone's `records()`, and there is no `Record::soa()`. Read it from the zone:

```php
$zone = Openprovider::zone('example.com')->get();

$zone->soa->value;  // "ns1.example.com dns.openprovider.eu 2026100803 10800 3600 604800 3600"
```

### Premium DNS

For a zone hosted by a premium DNS provider, name the provider after the zone. Everything after it works the same:

```php
use SpitsOnline\Openprovider\Enums\Provider;

Openprovider::zone('example.com')->provider(Provider::SECTIGO)->records()->add($record);
```

Records read from a premium zone remember their provider, so `$record->delete()` goes to the right place.

## Managing zones

```php
use SpitsOnline\Openprovider\Facades\Openprovider;

$zone = Openprovider::zone('example.com')->get();

$zone->id;          // 9146574
$zone->name;        // "example.com"
$zone->type;        // ZoneType::MASTER or ZoneType::SLAVE
$zone->isActive;    // true
$zone->provider;    // Provider::SECTIGO for premium DNS, otherwise null
$zone->records;     // list<ZoneRecord>
$zone->soa;         // the read-only SoaRecord
$zone->createdAt;   // CarbonImmutable or null, see "Dates and timezones"
$zone->modifiedAt;  // CarbonImmutable or null
$zone->raw;         // the full answer from Openprovider

$zone->toArray();   // the zone in Openprovider's own keys, as the `show` route returns it
```

List every zone lazily, a page at a time as you use them, and create or delete zones:

```php
Openprovider::zones()->get();
Openprovider::zones()->get(namePattern: 'example*', provider: Provider::SECTIGO)->take(10);

Openprovider::zones()->create('example.com', records: [Record::create(RecordType::A, '1.2.3.4')]);
Openprovider::zones()->create('example.com', dnssec: true, template: 'my-template', provider: Provider::SECTIGO);
Openprovider::zones()->createSlave('example.com', masterIp: '192.0.2.1');

Openprovider::zone('example.com')->delete();
```

`createSlave()` makes a slave zone, which copies its records from your own master server. Openprovider can't restore a deleted zone.

## Managing domains

Openprovider addresses a domain by its id. Pick one with `Openprovider::domain()`, which sends no request; every action after it sends one:

```php
use SpitsOnline\Openprovider\Facades\Openprovider;

Openprovider::domain(1222095)->renew();
```

With only the name, look the domain up first. That's one request, because Openprovider needs the id. The `Domain` you get back can act on itself, like an Eloquent model:

```php
$domain = Openprovider::domains()->find('example.com'); // or null

$domain->renew();
$domain->lock();
```

### Reading a domain

```php
$domain = Openprovider::domain(1222095)->get();

$domain->id;                     // 1222095
(string) $domain->name;           // "example.com"
$domain->name->name;              // "example"
$domain->name->extension;         // "com"
$domain->status;                  // "ACT" (active) or "REQ" (requested)
$domain->autorenew;               // Autorenew::ON, Autorenew::OFF or Autorenew::DEFAULT
$domain->nameServers;             // list<Nameserver>, each with a name, ip and ip6
$domain->nsGroup;                 // "dns-openprovider", the nameserver group it uses, or null
$domain->isLocked;                // true
$domain->isLockable;              // whether the registry lets it be locked against transfers
$domain->isPrivateWhoisEnabled;   // false
$domain->isDnssecEnabled;         // true
$domain->isSectigoDnsEnabled;     // whether it has an active premium DNS zone at Sectigo
$domain->isPremium;               // whether it has a premium price
$domain->owner?->fullName;        // "Jane Doe", also owner->companyName
$domain->ownerHandle;             // "CV904717-NL", also adminHandle, techHandle and billingHandle
$domain->orderDate;               // CarbonImmutable or null, see "Dates and timezones"
$domain->activeDate;              // CarbonImmutable or null
$domain->renewalDate;             // CarbonImmutable or null: renew before this date
$domain->expirationDate;          // CarbonImmutable or null
$domain->comments;                // your own notes on the domain, or null
$domain->raw;                     // the full answer from Openprovider
```

Rely on `renewalDate` for when a domain has to be renewed. Openprovider documents `expirationDate` as not being its primary reference for expiry.

List every domain lazily. The pattern matches the name without its extension:

```php
Openprovider::domains()->get(pattern: 'example*', status: 'ACT')->each(/* ... */);
```

Every method that takes a domain name accepts a string or a `DomainName`. `DomainName::parse()` splits a name the way Openprovider wants it, and throws `InvalidDomainName` for a name without an extension:

```php
use SpitsOnline\Openprovider\Data\DomainName;

$name = DomainName::parse('example.co.uk');
$name->name;       // "example"
$name->extension;  // "co.uk"
```

### Registering a domain

Check whether it's available first:

```php
$check = Openprovider::domains()->check(['example.com', 'example.nl'])[0];

$check->domain;         // "example.com"
$check->status;         // "free", "reserved" or "in use"
$check->isAvailable();  // true when the status is "free"
$check->isPremium;      // whether the registry charges a premium price
$check->raw;            // includes the price when you check with `withPrice: true`

Openprovider::domains()->check(['example.com'], withPrice: true);
```

Then register it. Contact handles are Openprovider customer handles. Openprovider charges your account for the registration.

```php
use SpitsOnline\Openprovider\Data\Nameserver;
use SpitsOnline\Openprovider\Enums\Autorenew;

$domain = Openprovider::domains()->register(
    'example.com',
    owner: 'CV904717-NL',
    admin: 'CV904717-NL',
    tech: 'CV904717-NL',
    billing: 'CV904717-NL',
    period: 1,
    nameServers: ['ns1.op.eu', Nameserver::create('ns2.example.com', ip: '192.0.2.2')],
    autorenew: Autorenew::ON,
);
```

Name servers are names, or `Nameserver` objects when they need a glue IP (`ip`, `ip6`). `Nameserver::from()` turns either into a `Nameserver`. Pass `nsGroup` instead to use a name server group from your Openprovider account.

To transfer a domain in, use `transfer()` with the same arguments, except `period`, plus its `authCode`:

```php
$domain = Openprovider::domains()->transfer('example.com', authCode: 'abc123', owner: 'CV904717-NL');
```

`register()`, `transfer()` and `update()` take an `attributes` array for any other field Openprovider documents for that request, such as `['promo_code' => 'SPRING']`.

### Changing a domain

`update()` only changes the arguments you pass:

```php
Openprovider::domain($id)->update(nsGroup: 'my-nameservers', autorenew: Autorenew::ON);
```

It also takes `nameServers`, `isLocked`, `isPrivateWhoisEnabled`, the four contact handles (`owner`, `admin`, `tech`, `billing`) and `comments`.

The other domain actions, the same on `Openprovider::domain($id)` and on a `Domain` you fetched:

```php
$domain->lock();     // can't be transferred away
$domain->unlock();
$domain->renew(period: 2);
$domain->restore();
$domain->delete();

$code = $domain->authCode();
$code = $domain->resetAuthCode();
```

A `Domain` or `ZoneRecord` in a queued job is serialized without the Openprovider client, so the password never ends up in your queue. After it's unserialized, it acts through the account in your config.

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

A record in a request body uses Openprovider's fields:

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

Invalid requests get Laravel's standard `422` validation response. Changes answer `204 No Content`. Each request may send a `provider` (`sectigo`) to work with a premium DNS zone. MX records need a `prio`.

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

You can also use the export in your own code. `ZoneExport::fromZone()` exports every record, the SOA record first; `new ZoneExport($records)` exports the records you pass:

```php
use Maatwebsite\Excel\Facades\Excel;
use SpitsOnline\Openprovider\Exports\ZoneExport;

$zone = Openprovider::zone('example.com')->get();

return Excel::download(ZoneExport::fromZone($zone), 'example.com.xlsx');
```

## Error handling

Every exception extends `SpitsOnline\Openprovider\Exceptions\OpenproviderException`:

| Exception | When |
|---|---|
| `RequestFailed` | Openprovider returned an error. `$status`, `$errorCode` and `$body` hold what it sent back. |
| `ConnectionFailed` | Openprovider couldn't be reached. |
| `MissingConfiguration` | The username or password isn't set. The message names the env key. |
| `InvalidDomainName` | A domain name has no extension, such as `localhost`. |
| `InvalidRecord` | `Record::fromArray()` gets an SOA record, which Openprovider generates and can't be added, changed or deleted. |
| `MissingDependency` | The export route is used without `maatwebsite/excel`. |

```php
use SpitsOnline\Openprovider\Exceptions\RequestFailed;

try {
    Openprovider::domain($id)->renew();
} catch (RequestFailed $e) {
    report($e);

    return back()->with('error', $e->body['desc'] ?? 'Openprovider failed');
}
```

## Testing your app

`Openprovider::fake()` swaps the client for an in-memory Openprovider. It answers the same requests Openprovider does, so everything in this README works on it, including `$record->delete()` and `$domain->renew()`. Seed it with zones and domains; changes apply to the seeded data, and you can assert on them:

```php
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Facades\Openprovider;

$fake = Openprovider::fake()
    ->withZone('example.com', Record::create(RecordType::A, '1.2.3.4'), Record::create(RecordType::MX, 'mail.example.com'))
    ->withDomain('example.com');
```

Run the code under test, then assert on what changed:

```php
Openprovider::assertRecordAdded(
    'example.com',
    fn (Record $record) => $record->value === '5.6.7.8',
);
Openprovider::assertRecordUpdated('example.com');
Openprovider::assertRecordDeleted('example.com', fn (Record $record) => $record->type === RecordType::MX);
Openprovider::assertZoneCreated('example.nl');
Openprovider::assertZoneDeleted('example.nl');
Openprovider::assertDomainRegistered('example.nl');
Openprovider::assertDomainTransferred('example.org');
Openprovider::assertDomainUpdated(1, fn (array $changes) => $changes === ['is_locked' => true]);
Openprovider::assertDomainRenewed(1);
Openprovider::assertDomainRestored(1);
Openprovider::assertDomainDeleted(1);
Openprovider::assertNothingChanged();
```

The record callbacks get each record as your code sent it. `assertDomainUpdated()` gets the changed fields in Openprovider's keys.

The fake stores records the way Openprovider does: under their full name (`www` becomes `www.example.com`) and with TXT values quoted. A zone or domain that wasn't seeded throws `RequestFailed` with status `404`. `check()` reports seeded domains as `in use` and every other name as `free`, and `authCode()` returns `OpenproviderFake::AUTH_CODE`.

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
