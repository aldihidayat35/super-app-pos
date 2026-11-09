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
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class ProductImportService
{
    public function __construct(
        private readonly ProductSkuService $skuService,
    ) {}

    /** @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>, total_count: int, valid_count: int, error_count: int} */
    public function preview(UploadedFile $file): array
    {
        @set_time_limit(300);

        $rows = $this->readRows($file);
        $errors = [];
        $enrichedRows = [];

        $validCategories = ProductCategory::query()
            ->get(['code', 'name'])
            ->flatMap(fn (ProductCategory $cat): array => array_filter([trim((string) $cat->code), trim((string) $cat->name)]))
            ->values()
            ->all();

        $validBrands = ProductBrand::query()
            ->get(['code', 'name'])
            ->flatMap(fn (ProductBrand $brand): array => array_filter([trim((string) $brand->code), trim((string) $brand->name)]))
            ->values()
            ->all();

        $validUnits = Unit::query()
            ->get(['code', 'symbol'])
            ->flatMap(fn (Unit $unit): array => array_filter([trim((string) $unit->code), trim((string) $unit->symbol)]))
            ->values()
            ->all();

        $rules = $this->rules($validCategories, $validBrands, $validUnits);
        $attributes = $this->attributes();

        foreach ($rows as $index => $row) {
            $rowNum = $index + 2;
            $validator = Validator::make($row, $rules, [], $attributes);
            $hasError = $validator->fails();
            $rowErrors = $hasError ? $validator->errors()->all() : [];

            if ($hasError) {
                $errors[$rowNum] = $rowErrors;
            }

            $row['__row_num'] = $rowNum;
            $row['__is_valid'] = ! $hasError;
            $row['__errors'] = $rowErrors;
            $enrichedRows[] = $row;
        }

        return [
            'rows' => $enrichedRows,
            'errors' => $errors,
            'total_count' => count($rows),
            'valid_count' => count($rows) - count($errors),
            'error_count' => count($errors),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function commit(array $rows): array
    {
        @set_time_limit(300);

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

            // Pre-load referensi ke memori untuk mencegah ribuan query N+1 saat import massal
            $categoryMap = [];
            foreach (ProductCategory::query()->get() as $cat) {
                $categoryMap[mb_strtoupper(trim((string) $cat->code))] = $cat;
                $categoryMap[mb_strtoupper(trim((string) $cat->name))] = $cat;
            }

            $unitMap = [];
            foreach (Unit::query()->get() as $u) {
                $unitMap[mb_strtoupper(trim((string) $u->code))] = $u;
                if (filled($u->symbol)) {
                    $unitMap[mb_strtoupper(trim((string) $u->symbol))] = $u;
                }
            }

            $brandMap = [];
            foreach (ProductBrand::query()->get() as $b) {
                $brandMap[mb_strtoupper(trim((string) $b->code))] = $b;
                $brandMap[mb_strtoupper(trim((string) $b->name))] = $b;
            }

            // Pre-load semua SKU yang sudah ada agar generasi SKU otomatis ribuan baris instan tanpa N+1 query
            $existingSkuMap = Product::query()->withTrashed()->pluck('id', 'sku')->all();
            $monthPrefix = now()->format('mY');
            $skuPrefix = "PRD{$monthPrefix}";
            $skuPrefixLen = strlen($skuPrefix);

            $maxSequence = 0;
            foreach ($existingSkuMap as $existingSku => $existingId) {
                $existingSkuStr = (string) $existingSku;
                if (str_starts_with($existingSkuStr, $skuPrefix)) {
                    $suffix = substr($existingSkuStr, $skuPrefixLen);
                    if (ctype_digit($suffix) && strlen($suffix) <= 6) {
                        $seq = (int) $suffix;
                        if ($seq > $maxSequence) {
                            $maxSequence = $seq;
                        }
                    }
                }
            }
            $nextSkuSequence = $maxSequence + 1;

            foreach ($rows as $row) {
                // Resolusi Kategori dari memory map
                $category = null;
                if (filled($row['category_code'] ?? null)) {
                    $catKey = mb_strtoupper(trim((string) $row['category_code']));
                    $category = $categoryMap[$catKey] ?? null;
                }
                $category = $category ?: $defaultCategory;

                // Resolusi Satuan dari memory map
                $unit = null;
                if (filled($row['base_unit_code'] ?? null)) {
                    $unitKey = mb_strtoupper(trim((string) $row['base_unit_code']));
                    $unit = $unitMap[$unitKey] ?? null;
                }
                $unit = $unit ?: $defaultUnit;

                // Resolusi Brand dari memory map
                $brand = null;
                if (filled($row['brand_code'] ?? null)) {
                    $brandKey = mb_strtoupper(trim((string) $row['brand_code']));
                    $brand = $brandMap[$brandKey] ?? null;
                }

                // Resolusi SKU: jika kosong, generate otomatis secara sekuensial & unik
                if (filled($row['sku'] ?? null)) {
                    $sku = trim((string) $row['sku']);
                } else {
                    $sku = $this->skuService->nextAvailableSku($skuPrefix, $nextSkuSequence, $existingSkuMap);
                }

                $attributes = [
                    'name' => trim((string) $row['name']),
                    'category_id' => $category->id,
                    'brand_id' => $brand?->id,
                    'base_unit_id' => $unit->id,
                    'status' => ProductStatus::ACTIVE->value,
                    'minimum_order' => $row['minimum_order'] ?: 0,
                    'minimum_stock' => $row['minimum_stock'] ?: 0,
                    'safety_stock' => $row['safety_stock'] ?: 0,
                ];

                if (! isset($existingSkuMap[$sku])) {
                    /** @var Product $product */
                    $product = Product::query()->create(array_merge(['sku' => $sku], $attributes));
                    $existingSkuMap[$sku] = $product->id;
                    $created++;
                } else {
                    /** @var Product $product */
                    $product = Product::query()->updateOrCreate(['sku' => $sku], $attributes);
                    $existingSkuMap[$sku] = $product->id;
                    $product->wasRecentlyCreated ? $created++ : $updated++;
                }

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

    /**
     * @param  list<string>  $validCategories
     * @param  list<string>  $validBrands
     * @param  list<string>  $validUnits
     * @return array<string, mixed>
     */
    private function rules(array $validCategories = [], array $validBrands = [], array $validUnits = []): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'category_code' => ['nullable', 'string', 'max:100', Rule::in($validCategories)],
            'brand_code' => ['nullable', 'string', 'max:100', Rule::in($validBrands)],
            'base_unit_code' => ['nullable', 'string', 'max:50', Rule::in($validUnits)],
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
