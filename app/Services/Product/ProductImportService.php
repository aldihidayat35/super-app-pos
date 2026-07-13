<?php

namespace App\Services\Product;

use App\Enums\ProductStatus;
use App\Models\Product;
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
            foreach ($rows as $row) {
                $category = ProductCategory::query()->where('code', $row['category_code'])->firstOrFail();
                $unit = Unit::query()->where('code', $row['base_unit_code'])->firstOrFail();
                $brand = filled($row['brand_code'] ?? null) ? ProductBrand::query()->where('code', $row['brand_code'])->first() : null;

                $product = Product::query()->updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'name' => $row['name'],
                        'category_id' => $category->id,
                        'brand_id' => $brand?->id,
                        'base_unit_id' => $unit->id,
                        'status' => $row['status'] ?: ProductStatus::ACTIVE->value,
                        'minimum_order' => $row['minimum_order'] ?: 0,
                        'minimum_stock' => $row['minimum_stock'] ?: 0,
                        'safety_stock' => $row['safety_stock'] ?: 0,
                    ],
                );

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
                $rows[] = collect($headers)->mapWithKeys(fn (string $header, int $index): array => [$header => $row[$index] ?? null])->all();
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
                return collect($headers)->mapWithKeys(fn (string $header, int $index): array => [$header => $row[$index] ?? null])->all();
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'category_code' => ['required', 'exists:product_categories,code'],
            'brand_code' => ['nullable', 'exists:product_brands,code'],
            'base_unit_code' => ['required', 'exists:units,code'],
            'status' => ['nullable', 'in:active,inactive,discontinued,preorder'],
            'minimum_order' => ['nullable', 'numeric', 'min:0'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'sku' => 'SKU',
            'name' => 'nama produk',
            'category_code' => 'kode kategori',
            'brand_code' => 'kode merek',
            'base_unit_code' => 'kode satuan dasar',
        ];
    }
}
