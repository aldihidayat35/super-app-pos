<?php

namespace App\Services\Product;

use App\Models\ProductBrand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class ProductBrandImportService
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
     * @return array{created: int, updated: int}
     */
    public function commit(array $rows): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated): void {
            foreach ($rows as $row) {
                $code = strtoupper(trim((string) $row['code']));
                $name = trim((string) $row['name']);
                $description = filled($row['description'] ?? null) ? trim((string) $row['description']) : null;
                $isActive = $this->parseBoolean($row['is_active'] ?? null, true);

                $brand = ProductBrand::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'description' => $description,
                        'is_active' => $isActive,
                    ]
                );

                $brand->wasRecentlyCreated ? $created++ : $updated++;
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
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable'],
        ];
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        return [
            'code' => 'kode merek',
            'name' => 'nama merek',
            'description' => 'deskripsi',
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
