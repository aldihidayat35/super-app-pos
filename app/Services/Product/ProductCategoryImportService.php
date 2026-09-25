<?php

namespace App\Services\Product;

use App\Models\ProductCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class ProductCategoryImportService
{
    /** @return array{rows: array<int, array<string, mixed>>, errors: array<int, array<int, string>>} */
    public function preview(UploadedFile $file): array
    {
        $rows = $this->readRows($file);
        $errors = [];
        $fileCodes = collect($rows)->pluck('code')->filter()->map(fn ($c) => strtoupper(trim((string) $c)))->all();

        foreach ($rows as $index => $row) {
            $validator = Validator::make($row, $this->rules(), [], $this->attributes());

            $rowErrors = $validator->errors()->all();

            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $parentCode = strtoupper(trim((string) ($row['parent_code'] ?? '')));

            if ($parentCode !== '') {
                if ($parentCode === $code) {
                    $rowErrors[] = 'Parent kategori tidak boleh sama dengan kode kategori itu sendiri.';
                } elseif (! in_array($parentCode, $fileCodes, true) && ! ProductCategory::query()->where('code', $parentCode)->exists()) {
                    $rowErrors[] = "Kode parent '{$parentCode}' tidak ditemukan di sistem maupun dalam file import.";
                }
            }

            if (! empty($rowErrors)) {
                $errors[$index + 2] = $rowErrors;
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int}
     */
    public function commit(array $rows): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated): void {
            // First pass: upsert category without parent_id so all records exist
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) $row['code']));
                $name = trim((string) $row['name']);
                $sortOrder = isset($row['sort_order']) && is_numeric($row['sort_order']) ? (int) $row['sort_order'] : 0;
                $icon = filled($row['icon'] ?? null) ? trim((string) $row['icon']) : null;
                $isActive = $this->parseBoolean($row['is_active'] ?? null, true);

                $category = ProductCategory::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'sort_order' => $sortOrder,
                        'icon' => $icon,
                        'is_active' => $isActive,
                    ]
                );

                $category->wasRecentlyCreated ? $created++ : $updated++;
            }

            // Second pass: link parent_id where parent_code is provided
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) $row['code']));
                $parentCode = strtoupper(trim((string) ($row['parent_code'] ?? '')));

                $parentId = null;
                if ($parentCode !== '') {
                    $parent = ProductCategory::query()->where('code', $parentCode)->first();
                    $parentId = $parent?->id;
                }

                ProductCategory::query()->where('code', $code)->update(['parent_id' => $parentId]);
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /** @return array<int, array<string, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        if (in_array(strtolower((string) $file->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            $handle = fopen($file->getRealPath(), 'r');
            $headers = array_map(fn (mixed $header): string => strtolower(trim((string) $header)), fgetcsv($handle) ?: []);
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
        $headers = collect(array_shift($sheet) ?: [])->map(fn (mixed $header): string => strtolower(trim((string) $header)))->all();

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
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'parent_code' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'icon' => ['nullable', 'string', 'max:60'],
            'is_active' => ['nullable'],
        ];
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'code' => 'kode kategori',
            'name' => 'nama kategori',
            'parent_code' => 'kode parent kategori',
            'sort_order' => 'urutan',
            'icon' => 'ikon',
            'is_active' => 'status aktif',
        ];
    }

    private function parseBoolean(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $str = strtolower(trim((string) $value));

        return in_array($str, ['1', 'true', 'yes', 'y', 'aktif', 'active'], true);
    }
}
