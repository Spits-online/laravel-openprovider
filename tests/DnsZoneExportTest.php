<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spits\LaravelOpenproviderApi\Http\Exports\DnsZoneExport;

/**
 * These tests pin the DnsZoneExport against both maatwebsite/excel ^3.1 and ^4.0.
 * The two majors declare different signatures on FromCollection::collection() and
 * WithStyles::styles(), so a change here can fatal on one major while passing on
 * the other. Writing a real .xlsx and reading it back catches that.
 */
function dnsRecords(): Collection
{
    return collect([
        ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'prio' => null, 'ttl' => 3600],
        ['name' => 'mail', 'type' => 'MX', 'value' => 'mx.example.test', 'prio' => 10, 'ttl' => 300],
    ])->map(fn (array $record) => (object) $record);
}

it('exposes the expected headings', function () {
    expect((new DnsZoneExport(dnsRecords()))->headings())
        ->toBe(['Name', 'Type', 'Value', 'Priority', 'TTL']);
});

it('maps a record onto the heading columns', function () {
    $export = new DnsZoneExport(dnsRecords());

    expect($export->map(dnsRecords()->first()))
        ->toBe(['www', 'A', '1.2.3.4', '-', 3600]);
});

it('falls back to a dash when a record has no priority', function () {
    $export = new DnsZoneExport(dnsRecords());

    expect($export->map((object) ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'prio' => null, 'ttl' => 60]))
        ->toContain('-');
});

it('registers an AfterSheet listener', function () {
    expect(array_keys((new DnsZoneExport(dnsRecords()))->registerEvents()))
        ->toContain(AfterSheet::class);
});

it('derives spreadsheet column names from a zero-based index', function () {
    $export = new DnsZoneExport(dnsRecords());

    expect($export->getExcelColumnName(0))->toBe('A')
        ->and($export->getExcelColumnName(4))->toBe('E')
        ->and($export->getExcelColumnName(26))->toBe('AA');
});

it('accepts an array, a Collection and a LazyCollection', function () {
    $record = (object) ['name' => 'www', 'type' => 'A', 'value' => '1.2.3.4', 'prio' => null, 'ttl' => 60];

    expect((new DnsZoneExport([$record]))->collection())
        ->toBeInstanceOf(Enumerable::class)
        ->toHaveCount(1)
        ->and((new DnsZoneExport(collect([$record])))->collection())
        ->toBeInstanceOf(Enumerable::class)
        ->toHaveCount(1);

    // An Enumerable is passed through untouched, so a lazy source stays lazy.
    $lazy = LazyCollection::make([$record]);
    expect((new DnsZoneExport($lazy))->collection())->toBe($lazy);
});

it('writes a real xlsx with the headings, mapped rows, styling and auto filter', function () {
    $raw = Excel::raw(new DnsZoneExport(dnsRecords()), ExcelFormat::XLSX);

    expect($raw)->toBeString()
        ->and(substr($raw, 0, 2))->toBe('PK'); // xlsx is a zip archive

    $path = tempnam(sys_get_temp_dir(), 'dns-zone-export').'.xlsx';
    file_put_contents($path, $raw);

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();

        // Headings from WithHeadings.
        expect($sheet->getCell('A1')->getValue())->toBe('Name')
            ->and($sheet->getCell('E1')->getValue())->toBe('TTL');

        // Rows from FromCollection + WithMapping.
        expect($sheet->getCell('A2')->getValue())->toBe('www')
            ->and($sheet->getCell('D2')->getValue())->toBe('-')
            ->and($sheet->getCell('A3')->getValue())->toBe('mail')
            ->and($sheet->getCell('D3')->getValue())->toBe(10);

        // Bold heading row from WithStyles.
        expect($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue();

        // Number formats applied by WithColumnFormatting and the AfterSheet listener.
        expect($sheet->getStyle('A1')->getNumberFormat()->getFormatCode())->toBe('@')
            ->and($sheet->getStyle('D1')->getNumberFormat()->getFormatCode())->toBe('0.00');

        // Auto filter set through the Sheet facade's delegated __call.
        expect($sheet->getAutoFilter()->getRange())->toBe('A1:E1');
    } finally {
        unlink($path);
    }
});
