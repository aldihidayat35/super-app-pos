<?php

namespace App\Services\System;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class InitialDataImportService
{
    /** @return array<string, array{label: string, columns: list<string>}> */
    public function templates(): array
    {
        return [
            'suppliers' => ['label' => 'Supplier', 'columns' => ['code', 'name', 'phone_number', 'email', 'payment_term_days']],
            'customers' => ['label' => 'Pelanggan', 'columns' => ['code', 'business_name', 'pic_name', 'phone_number', 'email', 'price_category', 'payment_term_days']],
            'products' => ['label' => 'Produk', 'columns' => ['sku', 'name', 'category_code', 'brand_code', 'base_unit_code', 'minimum_price', 'cost_price']],
            'opening_stocks' => ['label' => 'Stok Awal', 'columns' => ['product_sku', 'work_location_code', 'warehouse_location_code', 'base_quantity', 'hpp', 'reason']],
            'users' => ['label' => 'User', 'columns' => ['name', 'username', 'email', 'phone_number', 'role', 'work_location_code']],
            'locations' => ['label' => 'Gudang/Cabang', 'columns' => ['type', 'code', 'name', 'city', 'phone_number', 'default_warehouse_code']],
        ];
    }

    /** @return array{type: string, label: string, headers: list<string>, rows: list<array<string, string>>, errors: list<string>, totals: array{rows: int, valid_rows: int, invalid_rows: int}, dry_run: bool} */
    public function preview(string $type, UploadedFile $file, bool $dryRun = true): array
    {
        $templates = $this->templates();
        abort_unless(isset($templates[$type]), 404);

        $rows = $this->readCsv($file);
        $headers = $rows[0] ?? [];
        $body = array_slice($rows, 1, 50);
        $expected = $templates[$type]['columns'];
        $errors = [];

        if ($headers !== $expected) {
            $errors[] = 'Header CSV tidak sesuai template. Gunakan template resmi sebelum import.';
        }

        $mappedRows = [];
        foreach ($body as $index => $row) {
            $mapped = array_combine($headers, array_pad($row, count($headers), ''));

            $mapped = array_map(fn ($value): string => trim((string) $value), $mapped);
            $rowErrors = $this->validateRow($type, $mapped, $index + 2);
            $errors = array_merge($errors, $rowErrors);
            $mappedRows[] = $mapped;
        }

        return [
            'type' => $type,
            'label' => $templates[$type]['label'],
            'headers' => $headers,
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

    public function templateCsv(string $type): string
    {
        $templates = $this->templates();
        abort_unless(isset($templates[$type]), 404);

        return implode(',', $templates[$type]['columns']).PHP_EOL;
    }

    /** @return list<list<string>> */
    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath() ?: '', 'rb');

        if ($handle === false) {
            return [];
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = array_map(fn ($value): string => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
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

        $validator = Validator::make($row, $rules);

        return collect($validator->errors()->all())
            ->map(fn (string $error): string => "Baris {$line}: {$error}")
            ->all();
    }
}
