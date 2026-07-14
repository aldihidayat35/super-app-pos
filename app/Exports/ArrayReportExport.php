<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ArrayReportExport implements FromArray, WithHeadings
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $headings
     */
    public function __construct(private readonly array $rows, private readonly array $headings) {}

    /** @return list<array<int, mixed>> */
    public function array(): array
    {
        return array_map(
            fn (array $row): array => array_map(fn (string $heading): mixed => $row[$heading] ?? null, $this->headings),
            $this->rows,
        );
    }

    /** @return list<string> */
    public function headings(): array
    {
        return $this->headings;
    }
}
