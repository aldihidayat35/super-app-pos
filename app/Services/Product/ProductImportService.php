<?php

namespace App\Services\Product;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class ProductImportService
{
    public function __construct(
        private readonly ProductSkuService $skuService,
    ) {}

    /** @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>} */
    public function preview(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $errors = [];

        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, $this->rules(), [], $this->attributes());
            if ($validator->fails()) {
                $errors[$index + 2] = $validator->errors()->all();
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function commit(array $rows): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated): void {
            // Default Category jika kolom category_code kosong atau tidak ditemukan
            $defaultCategory = ProductCategory::query()->firstOrCreate(
                ['code' => 'UMUM'],
                ['name' => 'Umum', 'is_active' => true]
            );

            // Default Unit jika kolom base_unit_code kosong atau tidak ditemukan
            $defaultUnit = Unit::query()->where('code', 'PCS')->first()
                ?? Unit::query()->firstOrCreate(
                    ['code' => 'PCS'],
                    ['name' => 'Pieces', 'symbol' => 'pcs', 'is_active' => true]
                );

            foreach ($rows as $row) {
                // Resolusi Kategori: cari berdasarkan code atau nama, jika kosong gunakan default
                $category = null;
                if (filled($row['category_code'] ?? null)) {
                    $category = ProductCategory::query()
                        ->where('code', $row['category_code'])
                        ->orWhere('name', $row['category_code'])
                        ->first();
                }
                $category = $category ?: $defaultCategory;

                // Resolusi Satuan: cari berdasarkan code atau symbol, jika kosong gunakan PCS
                $unit = null;
                if (filled($row['base_unit_code'] ?? null)) {
                    $unit = Unit::query()
                        ->where('code', $row['base_unit_code'])
                        ->orWhere('symbol', $row['base_unit_code'])
                        ->first();
                }
                $unit = $unit ?: $defaultUnit;

                // Resolusi Brand: opsional
                $brand = null;
                if (filled($row['brand_code'] ?? null)) {
                    $brand = ProductBrand::query()
                        ->where('code', $row['brand_code'])
                        ->orWhere('name', $row['brand_code'])
                        ->first();
                }

                // Resolusi SKU: jika kosong, generate otomatis dengan format unik PRD{MMYY}{0001}
                $sku = filled($row['sku'] ?? null) ? trim((string) $row['sku']) : $this->skuService->generate();

                /** @var Product $product */
                $product = Product::query()->updateOrCreate(
                    ['sku' => $sku],
                    [
                        'name' => trim((string) $row['name']),
                        'category_id' => $category->id,
                        'brand_id' => $brand?->id,
                        'base_unit_id' => $unit->id,
                        'status' => ProductStatus::ACTIVE->value,
                        'minimum_order' => $row['minimum_order'] ?: 0,
                        'minimum_stock' => $row['minimum_stock'] ?: 0,
                        'safety_stock' => $row['safety_stock'] ?: 0,
                    ],
                );

                // Resolusi Barcode jika diisi di file Excel
                if (filled($row['barcode'] ?? null)) {
                    $barcode = trim((string) $row['barcode']);
                    $existingConflict = ProductBarcode::query()
                        ->where('code', $barcode)
                        ->where('product_id', '!=', $product->id)
                        ->exists();

                    if (! $existingConflict) {
                        ProductBarcode::query()->updateOrCreate(
                            [
                                'product_id' => $product->id,
                                'is_primary' => true,
                            ],
                            [
                                'code' => $barcode,
                                'type' => 'barcode',
                                'is_active' => true,
                            ]
                        );
                    }
                }

                $product->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /** @return array<int, array<string, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        if (in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            $handle = fopen($file->getRealPath(), 'r');
            $headers = array_map(fn (mixed $header): string => trim((string) $header), fgetcsv($handle) ?: []);
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (collect($row)->filter(fn (mixed $value): bool => filled($value))->isEmpty()) {
                    continue;
                }
                $rows[] = collect($headers)->mapWithKeys(function (string $header, int $index) use ($row): array {
                    $val = $row[$index] ?? null;
                    $trimmed = is_string($val) ? trim($val) : $val;

                    return [$header => ($trimmed === '' || $trimmed === null) ? null : $trimmed];
                })->all();
            }
            fclose($handle);

            return $rows;
        }

        /** @var array<int, array<int, array<int|string, mixed>>> $sheets */
        $sheets = Excel::toArray(new class implements ToArray
        {
            /** @param array<int, array<int|string, mixed>> $array */
            public function array(array $array): void
            {
                //
            }
        }, $file);
        $sheet = $sheets[0] ?? [];
        $headers = collect(array_shift($sheet) ?: [])->map(fn (mixed $header): string => trim((string) $header))->all();

        return collect($sheet)
            ->filter(fn (array $row): bool => collect($row)->filter(fn (mixed $value): bool => filled($value))->isNotEmpty())
            ->map(function (array $row) use ($headers): array {
                return collect($headers)->mapWithKeys(function (string $header, int $index) use ($row): array {
                    $val = $row[$index] ?? null;
                    $trimmed = is_string($val) ? trim($val) : $val;

                    return [$header => ($trimmed === '' || $trimmed === null) ? null : $trimmed];
                })->all();
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'category_code' => ['nullable', 'string', 'max:100', 'exists:product_categories,code'],
            'brand_code' => ['nullable', 'string', 'max:100', 'exists:product_brands,code'],
            'base_unit_code' => ['nullable', 'string', 'max:50', 'exists:units,code'],
            'minimum_order' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'name' => 'nama produk',
            'sku' => 'SKU',
            'barcode' => 'barcode',
            'category_code' => 'kode kategori',
            'brand_code' => 'kode merek',
            'base_unit_code' => 'kode satuan dasar',
            'minimum_order' => 'minimum order',
            'minimum_stock' => 'minimum stock',
            'safety_stock' => 'safety stock',
        ];
    }
}
