# Changelog

All notable changes to `laravel-openprovider-api` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.1.0]: https://github.com/Spits-online/laravel-openprovider-api/compare/V1.0.1...v1.1.0
[1.0.1]: https://github.com/Spits-online/laravel-openprovider-api/compare/v1.0.0...V1.0.1
[1.0.0]: https://github.com/Spits-online/laravel-openprovider-api/releases/tag/v1.0.0
