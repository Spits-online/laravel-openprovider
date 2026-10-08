<?php

declare(strict_types=1);

use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SpitsOnline\Openprovider\Data\Record;
use SpitsOnline\Openprovider\Enums\RecordType;
use SpitsOnline\Openprovider\Enums\Ttl;
use SpitsOnline\Openprovider\Exports\ZoneExport;

it('writes a real xlsx with a bold, filterable heading row and one row per record', function () {
    $export = new ZoneExport([
        Record::create(RecordType::A, '1.2.3.4', 'www'),
        Record::create(RecordType::Mx, 'mail.example.com', ttl: Ttl::Hour, prio: 10),
    ]);

    $path = tempnam(sys_get_temp_dir(), 'zone-export').'.xlsx';
    file_put_contents($path, Excel::raw($export, ExcelFormat::XLSX));

    try {
        $sheet = IOFactory::load($path)->getActiveSheet();

        expect($sheet->toArray())->toBe([
            ['Name', 'Type', 'Value', 'Priority', 'TTL'],
            ['www', 'A', '1.2.3.4', '-', '900'],
            [null, 'MX', 'mail.example.com', '10', '3600'],
        ])
            ->and($sheet->getStyle('A1')->getFont()->getBold())->toBeTrue()
            ->and($sheet->getAutoFilter()->getRange())->toBe('A1:E1');
    } finally {
        unlink($path);
    }
});
