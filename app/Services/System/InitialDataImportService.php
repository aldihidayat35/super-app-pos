<?php

namespace App\Services\System;

use App\Models\Product;
use App\Models\ProductCostHistory;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WorkLocation;
use App\Services\Inventory\InventoryService;
use App\Support\Decimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

final class InitialDataImportService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** @return array<string, array{label: string, columns: array<string, string>}> */
    public function templates(): array
    {
        return [
            'suppliers' => ['label' => 'Supplier', 'columns' => ['code' => 'Kode Supplier', 'name' => 'Nama Supplier', 'phone_number' => 'Nomor Telepon', 'email' => 'Email', 'payment_term_days' => 'Termin Pembayaran (Hari)']],
            'customers' => ['label' => 'Pelanggan', 'columns' => ['code' => 'Kode Pelanggan', 'business_name' => 'Nama Usaha', 'pic_name' => 'Nama PIC', 'phone_number' => 'Nomor Telepon', 'email' => 'Email', 'price_category' => 'Kategori Harga', 'payment_term_days' => 'Termin Pembayaran (Hari)']],
            'products' => ['label' => 'Produk', 'columns' => ['sku' => 'SKU Produk', 'name' => 'Nama Produk', 'category_code' => 'Kode Kategori', 'brand_code' => 'Kode Merek', 'base_unit_code' => 'Kode Satuan Dasar', 'minimum_price' => 'Harga Minimum', 'cost_price' => 'HPP']],
            'opening_stocks' => ['label' => 'Stok Awal', 'columns' => ['product_sku' => 'SKU Produk', 'work_location_code' => 'Kode Gudang/Cabang', 'warehouse_location_code' => 'Kode Lokasi Gudang', 'base_quantity' => 'Jumlah Stok Awal', 'hpp' => 'HPP', 'reason' => 'Alasan']],
            'users' => ['label' => 'User', 'columns' => ['name' => 'Nama Lengkap', 'username' => 'Username', 'email' => 'Email', 'phone_number' => 'Nomor Telepon', 'role' => 'Peran', 'work_location_code' => 'Kode Lokasi Kerja']],
            'locations' => ['label' => 'Gudang/Cabang', 'columns' => ['type' => 'Jenis Lokasi', 'code' => 'Kode Lokasi', 'name' => 'Nama Lokasi', 'city' => 'Kota', 'phone_number' => 'Nomor Telepon', 'default_warehouse_code' => 'Kode Gudang Default']],
        ];
    }

    /** @return array{type: string, label: string, headers: list<string>, header_labels: array<string, string>, rows: list<array<string, string>>, errors: list<string>, totals: array{rows: int, valid_rows: int, invalid_rows: int}, dry_run: bool} */
    public function preview(string $type, UploadedFile $file, bool $dryRun = true): array
    {
        $templates = $this->templates();
        abort_unless(isset($templates[$type]), 404);

        $rows = $this->readSpreadsheet($file);
        $headers = $rows[0] ?? [];
        $body = array_slice($rows, 1, 50);
        $columns = $templates[$type]['columns'];
        $expected = array_values($columns);
        $errors = [];

        if ($headers !== $expected) {
            $errors[] = 'Header Excel tidak sesuai template. Gunakan template XLSX resmi sebelum import.';
        }

        $mappedRows = [];
        foreach ($body as $index => $row) {
            $keys = array_keys($columns);
            $values = array_slice(array_pad($row, count($keys), ''), 0, count($keys));
            $mapped = array_combine($keys, $values);

            $mapped = array_map(fn ($value): string => trim((string) $value), $mapped);
            $rowErrors = $this->validateRow($type, $mapped, $index + 2);
            $errors = array_merge($errors, $rowErrors);
            $mappedRows[] = $mapped;
        }

        return [
            'type' => $type,
            'label' => $templates[$type]['label'],
            'headers' => array_keys($columns),
            'header_labels' => $columns,
            'rows' => $mappedRows,
            'errors' => $errors,
            'totals' => [
                'rows' => count($mappedRows),
                'valid_rows' => max(0, count($mappedRows) - count(array_unique(array_map(fn (string $error): string => strtok($error, ':') ?: $error, $errors)))),
                'invalid_rows' => count($errors),
            ],
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param  array{type: string, rows: list<array<string, string>>, errors: list<string>}  $preview
     * @return array{processed_rows: int, changed_rows: int, unchanged_rows: int, hpp_recorded_rows: int}
     */
    public function commit(array $preview, User $actor): array
    {
        if ($preview['type'] !== 'opening_stocks') {
            abort(422, 'Commit langsung saat ini hanya tersedia untuk Stok Awal.');
        }

        if ($preview['errors'] !== []) {
            abort(422, 'Data dengan error validasi tidak dapat di-commit.');
        }

        $rows = $preview['rows'];
        $changed = 0;
        $unchanged = 0;
        $hppRecorded = 0;
        $referenceNo = 'IMPORT-'.now()->format('Ymd-His');

        DB::transaction(function () use ($rows, $actor, $referenceNo, &$changed, &$unchanged, &$hppRecorded): void {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $product = Product::query()->where('sku', $row['product_sku'])->lockForUpdate()->first();
                $workLocation = WorkLocation::query()->where('code', $row['work_location_code'])->first();
                $warehouseLocation = $this->warehouseLocation($row['warehouse_location_code']);

                if (! $product || ! $workLocation || ! $warehouseLocation || ! $this->warehouseLocationMatches($workLocation, $warehouseLocation)) {
                    abort(422, "Referensi produk atau lokasi pada baris {$line} berubah. Lakukan preview ulang.");
                }

                $quantity = Decimal::normalize($row['base_quantity']);
                $hpp = Decimal::normalize($row['hpp'], 2);
                $hppBefore = Decimal::normalize((string) $product->cost_price, 2);
                $totalQuantityBefore = Decimal::normalize((string) Stock::query()->where('product_id', $product->id)->sum('quantity_on_hand'));
                $stock = Stock::query()
                    ->where('product_id', $product->id)
                    ->where('work_location_id', $workLocation->id)
                    ->where('warehouse_location_id', $warehouseLocation->id)
                    ->first();
                $costValue = Decimal::mul($quantity, $hpp);
                $quantityChanged = ! $stock || Decimal::compare((string) $stock->quantity_on_hand, $quantity) !== 0;
                $hppChanged = Decimal::compare($hppBefore, $hpp, 2) !== 0;
                $costValueChanged = ! $stock || Decimal::compare((string) $stock->cost_value, $costValue, 2) !== 0;

                if ($quantityChanged) {
                    $this->inventory->adjust(
                        product: $product,
                        workLocation: $workLocation,
                        warehouseLocation: $warehouseLocation,
                        targetOnHand: $quantity,
                        actor: $actor,
                        reference: ['type' => 'opening_stock', 'no' => $referenceNo],
                        reason: $row['reason'],
                        metadata: ['hpp' => $hpp, 'source' => 'initial_data_import'],
                    );
                }

                $product->forceFill(['cost_price' => $hpp])->saveOrFail();

                Stock::query()->where('product_id', $product->id)->lockForUpdate()->get()
                    ->each(function (Stock $productStock) use ($hpp): void {
                        $productStock->forceFill([
                            'cost_value' => Decimal::mul((string) $productStock->quantity_on_hand, $hpp),
                        ])->saveOrFail();
                    });

                if ($quantityChanged || $hppChanged) {
                    $totalQuantityAfter = Decimal::normalize((string) Stock::query()->where('product_id', $product->id)->sum('quantity_on_hand'));

                    ProductCostHistory::query()->create([
                        'product_id' => $product->id,
                        'method' => 'opening_stock',
                        'source_type' => 'opening_stock',
                        'source_reference' => $referenceNo,
                        'changed_by' => $actor->id,
                        'reason' => $row['reason'],
                        'qty_before' => $totalQuantityBefore,
                        'qty_incoming' => Decimal::sub($totalQuantityAfter, $totalQuantityBefore),
                        'qty_after' => $totalQuantityAfter,
                        'hpp_before' => $hppBefore,
                        'incoming_cost' => $costValue,
                        'landed_cost_allocated' => '0.00',
                        'hpp_after' => $hpp,
                        'effective_at' => now(),
                    ]);
                    $hppRecorded++;
                }

                if ($quantityChanged || $hppChanged || $costValueChanged) {
                    $changed++;
                } else {
                    $unchanged++;
                }
            }
        });

        return [
            'processed_rows' => count($rows),
            'changed_rows' => $changed,
            'unchanged_rows' => $unchanged,
            'hpp_recorded_rows' => $hppRecorded,
        ];
    }

    /** @return list<list<string>> */
    private function readSpreadsheet(UploadedFile $file): array
    {
        /** @var array<int, array<int, array<int|string, mixed>>> $sheets */
        $sheets = Excel::toArray(new class implements ToArray
        {
            /** @param array<int, array<int|string, mixed>> $array */
            public function array(array $array): void
            {
                // Data dikembalikan oleh Excel::toArray().
            }
        }, $file);

        return collect($sheets[0] ?? [])
            ->filter(fn (array $row): bool => collect($row)->contains(fn (mixed $value): bool => filled($value)))
            ->map(fn (array $row): array => array_map(fn (mixed $value): string => trim((string) $value), array_values($row)))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function validateRow(string $type, array $row, int $line): array
    {
        $rules = match ($type) {
            'suppliers' => ['code' => ['required', 'alpha_dash'], 'name' => ['required'], 'email' => ['nullable', 'email'], 'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365']],
            'customers' => ['code' => ['required', 'alpha_dash'], 'business_name' => ['required'], 'email' => ['nullable', 'email'], 'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365']],
            'products' => ['sku' => ['required', 'alpha_dash'], 'name' => ['required'], 'minimum_price' => ['nullable', 'numeric', 'min:0'], 'cost_price' => ['nullable', 'numeric', 'min:0']],
            'opening_stocks' => ['product_sku' => ['required'], 'work_location_code' => ['required'], 'base_quantity' => ['required', 'numeric', 'min:0'], 'hpp' => ['required', 'numeric', 'min:0'], 'reason' => ['required']],
            'users' => ['name' => ['required'], 'username' => ['required', 'alpha_dash'], 'email' => ['required', 'email'], 'role' => ['required']],
            'locations' => ['type' => ['required', Rule::in(['warehouse', 'branch'])], 'code' => ['required', 'alpha_dash'], 'name' => ['required']],
            default => [],
        };

        $validator = Validator::make($row, $rules, [], $this->templates()[$type]['columns'] ?? []);

        $errors = collect($validator->errors()->all())
            ->map(fn (string $error): string => "Baris {$line}: {$error}")
            ->all();

        if ($type === 'opening_stocks' && $errors === []) {
            $product = Product::query()->where('sku', $row['product_sku'])->first();
            $workLocation = WorkLocation::query()->where('code', $row['work_location_code'])->first();
            $warehouseLocation = $this->warehouseLocation($row['warehouse_location_code'] ?? '');

            if (! $product) {
                $errors[] = "Baris {$line}: SKU produk tidak ditemukan.";
            }
            if (! $workLocation) {
                $errors[] = "Baris {$line}: kode gudang/cabang tidak ditemukan.";
            }
            if (! $warehouseLocation) {
                $errors[] = "Baris {$line}: kode lokasi gudang tidak ditemukan.";
            } elseif ($workLocation && ! $this->warehouseLocationMatches($workLocation, $warehouseLocation)) {
                $errors[] = "Baris {$line}: lokasi rak tidak termasuk dalam gudang yang dipilih.";
            }
        }

        return $errors;
    }

    private function warehouseLocation(string $code): ?WarehouseLocation
    {
        return WarehouseLocation::query()
            ->where('full_code', $code)
            ->orWhere('code', $code)
            ->first();
    }

    private function warehouseLocationMatches(WorkLocation $workLocation, WarehouseLocation $warehouseLocation): bool
    {
        return Warehouse::query()
            ->whereKey($warehouseLocation->warehouse_id)
            ->where('work_location_id', $workLocation->id)
            ->exists();
    }
}
