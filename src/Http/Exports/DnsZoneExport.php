<?php

namespace Spits\LaravelOpenproviderApi\Http\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
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

class DnsZoneExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithStyles
{
    protected Enumerable $collection;

    protected $columns = 5;

    public function __construct($collection)
    {
        $this->collection = $collection instanceof Enumerable
            ? $collection
            : Collection::make($collection);
    }

    public function collection(): Enumerable
    {
        return $this->collection;
    }

    public function headings(): array
    {
        return [
            'Name',
            'Type',
            'Value',
            'Priority',
            'TTL',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'borders' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => ''],
                    ],
                ],
            ],
        ];
    }

    public function map($record): array
    {
        return [
            $record->name,
            $record->type,
            $record->value,
            $record->prio ?? '-',
            $record->ttl,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER_00,
            'E' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function getExcelColumnName($index)
    {
        $letters = '';
        while ($index >= 0) {
            $letters = chr($index % 26 + 65).$letters;
            $index = intval($index / 26) - 1;
        }

        return $letters;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $worksheet = $event->sheet->getDelegate();

                // Get the column formats
                $formats = $this->columnFormats();

                // Apply each format to the entire column
                foreach ($formats as $column => $format) {
                    $worksheet->getStyle("{$column}1:{$column}1048576")->getNumberFormat()->setFormatCode($format);
                }

                $event->sheet->setAutoFilter('A1:'.$this->getExcelColumnName($this->columns - 1).'1');
            },
        ];
    }
}
