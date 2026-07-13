<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseOrdersExport implements FromCollection, WithHeadings
{
    /** @param Collection<int, mixed> $purchaseOrders */
    public function __construct(private readonly Collection $purchaseOrders) {}

    /** @return Collection<int, array{0: mixed, 1: mixed, 2: mixed, 3: mixed, 4: mixed, 5: mixed, 6: mixed, 7: mixed, 8: mixed, 9: mixed}> */
    public function collection(): Collection
    {
        return $this->purchaseOrders->map(fn ($po): array => [
            $po->number,
            $po->supplier?->name,
            $po->warehouse?->name,
            $po->order_date?->format('Y-m-d'),
            $po->expected_at?->format('Y-m-d'),
            $po->grand_total,
            $po->orderedQuantity(),
            $po->receivedQuantity(),
            $po->outstandingQuantity(),
            $po->status->label(),
        ])->values();
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Nomor', 'Supplier', 'Gudang', 'Tanggal', 'ETA', 'Total', 'Ordered', 'Received', 'Outstanding', 'Status'];
    }
}
