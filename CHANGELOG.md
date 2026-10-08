# Changelog

All notable changes to `laravel-openprovider` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-10-08

### Added
- `Openprovider::zones()` to list, find, create and delete DNS zones, walk their records lazily, and add, update and remove records.
- `Openprovider::domains()` to list, find, check, register, transfer, update, renew, restore and delete domains, and to get or reset their auth code. `findByName()` finds a domain by its full name.
- Typed `Zone`, `Record`, `Domain`, `DomainName`, `Nameserver`, `DomainCheck` and `Page` objects, each with the full answer in `$raw`, and `RecordType`, `Ttl`, `ZoneType` and `Autorenew` enums.
- `all()` and `records()` return lazy collections that fetch one page at a time.
- `Openprovider::fake()`: an in-memory Openprovider for testing apps, with `assertRecordAdded()`, `assertDomainRegistered()` and the other assertions.
- Exceptions that say what went wrong: `RequestFailed` (with Openprovider's `$status`, `$errorCode` and `$body`), `ConnectionFailed`, `MissingConfiguration` (names the env key to set), `InvalidDomainName` and `MissingDependency`, all extending `OpenproviderException`.
- An app's `config/openprovider.php` only needs the keys it changes. It is merged over the defaults key by key.
- Support for Openprovider's sandbox through `OPENPROVIDER_BASE_URL`.

### Changed
- **Breaking:** the package is renamed to `spits-online/laravel-openprovider`.
- **Breaking:** the namespace is now `SpitsOnline\Openprovider` instead of `Spits\LaravelOpenproviderApi`.
- **Breaking:** requires PHP 8.3+ and Laravel 12 or 13.
- **Breaking:** the config file is `config/openprovider.php` instead of `config/openprovider-api.php`, and the route middleware moved from `middleware` to `routes.middleware`.
- **Breaking:** `DomainService` and `DnsService` are replaced by `Openprovider::domains()` and `Openprovider::zones()`. These return typed objects and throw on failure instead of returning the raw HTTP response.
- **Breaking:** the DNS record routes are off until an app sets `routes.enabled`, and run behind the `web` and `auth` middleware by default.
- **Breaking:** the DNS record routes answer changes with `204 No Content`, and invalid requests with Laravel's standard validation response. `show` returns the zone as `data`, and no longer takes an `options` query parameter.
- **Breaking:** new records must use a TTL Openprovider accepts (900, 3600, 10800, 21600, 43200 or 86400 seconds). Openprovider saved any other value as a day.
- **Breaking:** the export route is off until an app sets `exports.enabled`, with its own `exports.prefix` and `exports.middleware` (`web` and `auth` by default), and `maatwebsite/excel` is optional. Install it (the export is tested against 4.x) to use the export route.
- **Breaking:** `DnsRecordTypes` is replaced by the `RecordType` enum, and `DnsZoneExport` by `ZoneExport`, which takes `Record` objects.
- The login token is cached for 47 hours (Openprovider's tokens are valid for 48) instead of 8, per account.

### Removed
- **Breaking:** `OpenproviderClient`, `OpenproviderAuth`, `DomainController` and the three DNS record form requests.
- **Breaking:** the `LaravelOpenproviderApi` facade alias, which pointed at a class that didn't exist.

### Fixed
- The DNS record routes were registered without any middleware by default, so anyone could read and change DNS records.
- Errors from Openprovider were returned to the browser with status 200. They now throw `RequestFailed`.
- The MX priority rule checked a field that doesn't exist, so MX records could be sent without a priority.
- Removing records accepted an empty list.
- The export crashed when Openprovider returned an error.

## [1.1.0] - 2026-08-26

### Added

- Support for `maatwebsite/excel` `^4.0` alongside `^3.1`. Composer resolves whichever major fits the
  platform, so nothing is dropped: PHP 8.2, Laravel 11 and Laravel Excel 3 all keep working.
- PHP 8.5 to the test matrix. Together with the above, this is what actually makes PHP 8.5 usable —
  1.0.1 already allowed `^8.5` in `composer.json`, but `maatwebsite/excel` `^3.1` could never resolve
  there because its `phpoffice/phpspreadsheet` 1.x dependency caps at `<8.5`. Note that PHP 8.5 needs
  Laravel 12 or 13: Laravel Excel 3 cannot install on 8.5 and Laravel Excel 4 requires Laravel 12+,
  so PHP 8.5 with Laravel 11 has no resolvable dependency set and is excluded from the matrix.
- Test coverage for `DnsZoneExport`, including a test that writes a real `.xlsx` and reads it back to
  assert the headings, mapped rows, styling, number formats and auto filter. It pins the export
  against both Laravel Excel majors.
- `Maatwebsite\Excel\ExcelServiceProvider` to the test suite's package providers, without which the
  export cannot be exercised under Testbench.

### Changed

- `DnsZoneExport::collection()` now declares `: Enumerable` and `DnsZoneExport::styles()` declares
  `: ?array`, as required by Laravel Excel 4's typed concern interfaces. Both signatures remain
  compatible with Laravel Excel 3.
- `DnsZoneExport::__construct()` normalises its argument to an `Enumerable`, so plain arrays keep
  working while an `Enumerable` is passed through untouched (a `LazyCollection` stays lazy). The
  parameter is deliberately left untyped so the public API is not narrowed.

### Removed

- The `version` key from `composer.json`. Packagist derives the version from Git tags, so keeping the
  field in sync by hand only invites drift.

## [1.0.1] - 2026-04-21

### Added

- Laravel 13 support, and PHP 8.4 / 8.5 to the `php` constraint.
- An explicit `illuminate/support` requirement (`^11.0||^12.0||^13.0`); it was previously only pulled
  in transitively.
- Laravel 13 to the test matrix, with PHP 8.2 excluded because Laravel 13 requires PHP 8.3+.

### Changed

- `DnsRecordsController` reads the `options` parameter with `request()->input()` instead of
  `request()->get()`.
- Widened the dev requirements to `orchestra/testbench` `^10.3||^11.0` and `pestphp/pest`
  `^3.0||^4.0`.
- Bumped `actions/checkout` to v6.

### Fixed

- PHPStan no longer analyses the non-existent `database` path, and `env()` calls inside `config/` are
  ignored rather than reported.

## [1.0.0] - 2025-05-22

### Added

- Initial release.
- `OpenproviderClient` and `OpenproviderAuth` for authenticated access to the Openprovider API.
- `DomainService` and `DnsService`, exposed through `DomainController` and `DnsRecordsController`.
- Routes for reading, creating, updating and deleting DNS zone records, guarded by the configurable
  `openprovider-api.middleware` stack.
- Form request validation for DNS record writes, backed by the `DnsRecordTypes` enum.
- `DnsZoneExport` for exporting a DNS zone to `.xlsx` via `maatwebsite/excel`.
- Publishable `config/openprovider-api.php`.

[Unreleased]: https://github.com/Spits-online/laravel-openprovider/compare/V2.0.0...HEAD
[2.0.0]: https://github.com/Spits-online/laravel-openprovider/compare/v1.1.0...V2.0.0
[1.1.0]: https://github.com/Spits-online/laravel-openprovider/compare/V1.0.1...v1.1.0
[1.0.1]: https://github.com/Spits-online/laravel-openprovider/compare/v1.0.0...V1.0.1
[1.0.0]: https://github.com/Spits-online/laravel-openprovider/releases/tag/v1.0.0
