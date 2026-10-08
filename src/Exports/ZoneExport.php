<?php

declare(strict_types=1);

namespace SpitsOnline\Openprovider\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use SpitsOnline\Openprovider\Data\Record;

/**
 * The records of a DNS zone as a spreadsheet: one row per record, with a bold,
 * filterable heading row. Needs `maatwebsite/excel`.
 *
 * @implements WithMapping<Record>
 */
class ZoneExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  iterable<array-key, Record>  $records
     */
    public function __construct(
        protected iterable $records,
    ) {}

    /**
     * @return Collection<int, Record>
     */
    public function collection(): Collection
    {
        return Collection::make($this->records)->values();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Name', 'Type', 'Value', 'Priority', 'TTL'];
    }

    /**
     * @param  Record  $row
     * @return list<int|string>
     */
    public function map(mixed $row): array
    {
        return [$row->name, $row->type->value, $row->value, $row->prio ?? '-', $row->ttl];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [1 => [
            'font' => ['bold' => true],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN]],
        ]];
    }

    /**
     * @return array<class-string, callable(AfterSheet): void>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $event->sheet->getDelegate()->setAutoFilter('A1:E1');
            },
        ];
    }
}
