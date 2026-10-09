# Upgrade guide

## From 1.x to 2.0

Version 2 is a rewrite. Version 1 is no longer supported and won't get bug or security fixes.

### Checklist

- [ ] You're on PHP 8.3+ and Laravel 12 or 13
- [ ] Switch to the new package name: `composer remove spits-online/laravel-openprovider-api`, then `composer require spits-online/laravel-openprovider:^2.0`
- [ ] Replace `Spits\LaravelOpenproviderApi\` with `SpitsOnline\Openprovider\` across your app
- [ ] Rename `config/openprovider-api.php` to `config/openprovider.php` and shrink it to the keys you change (see below)
- [ ] Using the `dns-zone/*` routes? Enable them in the config (see below)
- [ ] Using the export route? `composer require maatwebsite/excel` and set `exports.enabled` (see below)
- [ ] Replace `DomainService` and `DnsService` calls (see below)

The env keys (`OPENPROVIDER_USERNAME`, `OPENPROVIDER_PASSWORD`, `OPENPROVIDER_IP` and `OPENPROVIDER_BASE_URL`) haven't changed.

### Configuration

The config file is now `config/openprovider.php`. It's merged over the package defaults key by key, so keep only what you change. The credentials and base URL come from the same env keys as before, so most apps can delete their config file entirely.

### DNS record routes

The routes used to be registered in every app, with no middleware unless you set `openprovider-api.middleware`. They are now off until you enable them, and run behind `web` and `auth` by default.

Before:

```php
// config/openprovider-api.php
'middleware' => ['web', 'auth'],
```

After:

```php
// config/openprovider.php
return [
    'routes' => [
        'enabled' => true,
    ],
];
```

The export route (`dns-zone.export`) is not part of this group. It has its own switch, prefix and middleware under `exports`:

```php
// config/openprovider.php
return [
    'exports' => [
        'enabled' => true,
    ],
];
```

If you used other middleware, set `routes.middleware` to it. The URIs and route names are unchanged. What the routes send back did change:

| Route | 1.x | 2.0 |
|---|---|---|
| `GET dns-zone/records/{domain}` | Openprovider's full answer, with `data.records` | `{"data": {...}}`, with `data.records` |
| `POST`, `PUT`, `DELETE` | Openprovider's answer, with status 200 even on errors | `204 No Content`; errors throw |
| Invalid request | `{"name": "first error", ...}` | Laravel's standard `422` response: `{"message": ..., "errors": {"record.name": [...]}}` |

`show` no longer reads an `options` query parameter; it always returns the records. New records must use a TTL Openprovider accepts: 900, 3600, 10800, 21600, 43200 or 86400. Openprovider saved any other value as a day.

### Domains

`DomainService` is replaced by `Openprovider::domain($id)` for one domain and `Openprovider::domains()` for all of them. Methods return typed objects and throw `RequestFailed` or `ConnectionFailed` instead of returning the HTTP response.

| 1.x | 2.0 |
|---|---|
| `(new DomainService)->getDomains($options)` → `Response` | `Openprovider::domains()->get(...)` → lazy collection of `Domain` |
| `(new DomainService)->getDomain($id)` → `Response` | `Openprovider::domain($id)->get()` → `Domain` |
| `(new DomainService)->updateDomain($id, $data)` → `Response` | `Openprovider::domain($id)->update(...)` → `void` |

`update()` takes named arguments for the common fields. Pass anything else in `attributes`:

```php
// Before
(new DomainService)->updateDomain($id, ['ns_group' => 'my-ns']);

// After
Openprovider::domain($id)->update(nsGroup: 'my-ns');
```

### DNS zones

`DnsService` is replaced by `Openprovider::zone($domain)`, and records are changed through its `records()`.

| 1.x | 2.0 |
|---|---|
| `$service->getDnsZone($domain, ['with_records' => 'true'])` | `Openprovider::zone($domain)->get()` → `Zone` |
| `$service->updateDnsZone($domain, ['records' => ['add' => [...]]])` | `Openprovider::zone($domain)->records()->add(...)` |
| `... ['records' => ['update' => [...]]]` | `Openprovider::zone($domain)->records()->update($original, $record)`, or `$record->update($new)` |
| `... ['records' => ['remove' => [...]]]` | `Openprovider::zone($domain)->records()->delete(...)`, or `$record->delete()` |

Records are built per type instead of as arrays:

```php
// Before
$service->updateDnsZone($domain, ['records' => ['add' => [
    ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'ttl' => 900],
]]]);

// After
Openprovider::zone($domain)->records()->add(Record::create(RecordType::A, '1.2.3.4', name: 'www'));
```

`DnsRecordTypes::MX` becomes `RecordType::MX`: only the class name changes.

The zone's SOA record is no longer in its records, because it can't be changed. Read it from `Openprovider::zone($domain)->get()->soa`.

### Export

`DnsZoneExport` is now `ZoneExport`:

```php
// Before
new DnsZoneExport(collect($records)->map(fn ($r) => (object) $r));

// After
ZoneExport::fromZone(Openprovider::zone($domain)->get());
```

### Error handling

Every exception extends `SpitsOnline\Openprovider\Exceptions\OpenproviderException`. A failed login used to throw a plain `Exception`; it now throws `RequestFailed`.
